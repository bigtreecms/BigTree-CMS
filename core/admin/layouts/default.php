<?
	function _local_findPath($nav,$path,$last_link = "") {
		global $bigtree,$breadcrumb;
		foreach ($nav as $item) {
			// Doing this $last_link thing to make sure the View Whatever... actions don't appear as parents of Add/Edit.
			if (strpos($path,$item["link"]) === 0 && ($item["link"] != $last_link || $path == $item["link"])) {
				$breadcrumb[] = array("title" => $item["title"],"link" => $item["link"]);
				$bigtree["page"]["title"] = $item["title"] ? $item["title"] : $bigtree["page"]["title"];
				$bigtree["page"]["title"] = $item["title_override"] ? $item["title_override"] : $bigtree["page"]["title"];
				$bigtree["page"]["icon"] = $item["icon"] ? $item["icon"] : $bigtree["page"]["icon"];
				$bigtree["page"]["navigation"] = $item["children"] ? $item["children"] : $bigtree["page"]["navigation"];
				// Get the related dropdown menu
				if ($item["related"]) {
					$bigtree["page"]["related"]["title"] = $bigtree["page"]["title"];
					$bigtree["page"]["related"]["nav"] = $bigtree["page"]["navigation"];
				}
				if ($item["children"]) {
					_local_findPath($item["children"],$path,$item["link"]);
				}
			}
		}
	}

	$bigtree["page"] = array("navigation" => array(),"related" => array());
	$breadcrumb = array();
	$current_path = implode("/",array_slice($bigtree["path"],1));
	if (!defined("BIGTREE_ACCESS_DENIED")) {
		_local_findPath($bigtree["nav_tree"],$current_path);
	}

	// Set the page title if it hasn't been set
	if (!$module_title) {
		$module_title = $bigtree["page"]["title"];
	}

	// If we're in a module, add "Modules" to the beginning of the breadcrumb
	if (defined("MODULE_ROOT")) {
		$breadcrumb = array_merge(array(array("title" => "Modules","link" => "modules")),$breadcrumb);
	}

	// Replace breadcrumb with a custom one if it exists
	if (is_array($bigtree["breadcrumb"]) && count($bigtree["breadcrumb"])) {
		$breadcrumb = $bigtree["breadcrumb"];
	}

	// Allow individual pages to override the automatic navigation.
	if (isset($bigtree["page_override"])) {
		foreach ($bigtree["page_override"] as $key => $val) {
			$bigtree["page"][$key] = $val;
		}
	}

	include BigTree::path("admin/layouts/_header.php");
?>
<ul class="breadcrumb">
	<?
		$x = 0;
		foreach ($breadcrumb as $item) {
			$x++;
			
	?>
	<li<? if ($x == 1) { ?> class="first"<? } ?>>
		<a href="<?=ADMIN_ROOT.$item["link"]?>/"<? if ($x == count($breadcrumb)) { ?> class="last"<? } ?>><?=htmlspecialchars(htmlspecialchars_decode($item["title"]))?></a>
	</li>
	<?
			if ($x != count($breadcrumb)) {
	?>
	<li>&rsaquo;</li>
	<?		
			}
		}
	?>
</ul>
<div id="page">
	<?
		if ($bigtree["page"]["title"] && !defined("BIGTREE_404")) {
	?>
	<h1>
		<span class="<?=$bigtree["page"]["icon"]?>"><? if ($bigtree["page"]["icon"] == "gravatar") { ?><img src="<?=BigTree::gravatar($gravatar_email)?>" alt="" /><? } ?></span>
		<?
			echo htmlspecialchars(htmlspecialchars_decode(str_replace("View ","",$bigtree["page"]["title"])));

			// If we're in a module and have related modules, use them for the related nav.
			if (isset($bigtree["related_modules"])) {
				$bigtree["page"]["related"]["nav"] = $bigtree["related_modules"];
				$bigtree["page"]["related"]["title"] = $bigtree["related_group"];
			}
			// Draw the related nav if it exists.
			if (isset($bigtree["page"]["related"]["nav"])) {
		?>
		<nav class="jump_group">
			<span class="icon"></span>
			<nav class="dropdown">
				<strong><?=$bigtree["page"]["related"]["title"]?></strong>
				<?
					foreach ($bigtree["page"]["related"]["nav"] as $item) {
						if ($item["level"] <= $admin->Level) {
				?>
				<a href="<?=ADMIN_ROOT.$item["link"]?>/"><?=$item["title"]?></a>
				<?
						}
					}
				?>
			</nav>
		</nav>
		<?
			}
		?>
	</h1>
	<?
		}

		$show_nav = false;
		foreach ($bigtree["page"]["navigation"] as $item) {
			if (!$item["hidden"]) {
				$show_nav = true;
			}
		}
		if ($show_nav && !defined("BIGTREE_404")) {
	?>
	<nav class="sub">
		<ul>
			<?
				$active_item = false;
				// Figure out what the active state is.
				foreach ($bigtree["page"]["navigation"] as $item) {
					if (strpos($current_path,$item["link"]) !== false) {
						// If we already have an active item, see if the new one is deeper in the paths.
						if (!$active_item) {
							$active_item = $item;
						} else {
							if (strlen($item["link"]) > strlen($active_item["link"])) {
								$active_item = $item;
							}
						}
					}
				}
				// Draw the nav.
				foreach ($bigtree["page"]["navigation"] as $item) {
					if (!$item["hidden"] && (!$item["level"] || $item["level"] <= $admin->Level)) {
						$get_string = "";
						if (is_array($item["get_vars"]) && count($item["get_vars"])) {
							$get_string = "?";
							foreach ($item["get_vars"] as $key => $val) {
								$get_string .= "$key=".urlencode($val)."&";
							}
						}
			?>
			<li><a href="<?=ADMIN_ROOT.$item["link"]?>/<?=htmlspecialchars(rtrim($get_string,"&"))?>"<? if ($active_item == $item) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=($item["nav_icon"] ? $item["nav_icon"] : $item["icon"])?>"></span><?=$item["title"]?></a></li>
			<?
					}
				}
			?>
		</ul>
	</nav>
	<?
		}

		echo $bigtree["content"];
	?>
</div>
<? include BigTree::path("admin/layouts/_footer.php") ?>