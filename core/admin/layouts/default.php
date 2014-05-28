<?
	function _local_findPath($nav,$path,$last_link = "") {
		global $bigtree,$breadcrumb;
		foreach ($nav as $item) {
			if ((strpos($path,$item["link"]."/") === 0 && $item["link"] != $last_link) || $path == $item["link"]) {				
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
	if (!$bigtree["admin_title"]) {
		$bigtree["admin_title"] = $bigtree["page"]["title"];
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

	// If this is a "Partial" page request then we're going to deliver JSON and let JavaScript construct it.
	if ($_SERVER["HTTP_BIGTREE_PARTIAL"]) {
		ob_start();
		foreach ($breadcrumb as &$item) {
			$item["title"] = BigTree::safeEncode($item["title"]);
		}
		unset($item);
		// We're going to fake include the header to get the active nav state.
		include BigTree::path("admin/layouts/_header.php");
		ob_clean();
	// Otherwise, full page render, so include the header and draw the breadcrumb.
	} else {
		include BigTree::path("admin/layouts/_header.php");
	}
?>
<div id="page">
	<?
		if ($bigtree["page"]["title"] && !defined("BIGTREE_404")) {
	?>
	<h1>
		<span class="page_icon <?=$bigtree["page"]["icon"]?>"><? if ($bigtree["page"]["icon"] == "gravatar") { ?><img src="<?=BigTree::gravatar($bigtree["gravatar"])?>" alt="" /><? } ?></span>
		<?
			$x = 0;
			foreach ($breadcrumb as $item) {
				$x++;
				
		?>
		<a href="<?=ADMIN_ROOT.$item["link"]?>/" class="<? if ($x == 1) { ?> first<? } if ($x == count($breadcrumb)) { ?> last<? } ?>"><?=BigTree::safeEncode($item["title"])?></a>
		<?
				if ($x != count($breadcrumb)) {
		?>
		<span class="divider">&rsaquo;</span>
		<?		
				}
			}

			// If we're in a module and have related modules, use them for the related nav.
			if (isset($bigtree["related_modules"])) {
				$bigtree["page"]["related"]["nav"] = $bigtree["related_modules"];
				$bigtree["page"]["related"]["title"] = $bigtree["related_group"];
			}
			// Draw the related nav if it exists.
			if (isset($bigtree["page"]["related"]["nav"])) {
		?>
		<nav class="jump_group">
			<span class="icon">Related</span>
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
	<nav id="sub_nav">
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
		<a href="<?=ADMIN_ROOT.$item["link"]?>/<?=htmlspecialchars(rtrim($get_string,"&"))?>"<? if ($active_item == $item) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=($item["nav_icon"] ? $item["nav_icon"] : $item["icon"])?>"></span><?=$item["title"]?></a>
		<?
				}
			}
		?>
		<menu<? if (!count($bigtree["subnav_extras"])) { ?> style="display: none;"<? } ?>>
			<span class="icon"></span>
			<div>
				<?
					if (is_array($bigtree["subnav_extras"])) {
						foreach ($bigtree["subnav_extras"] as $link) {
							if ($admin->Level >= $link["level"]) {
				?>
				<a href="<?=$link["link"]?>"><span class="icon_small icon_small_<?=$link["icon"]?>"></span><?=$link["title"]?></a>
				<?
							}
						}
					}
				?>
			</div>
		</menu>
	</nav>
	<script>
		// Placing this here inline because we want the menu rendering changed on page render if it's too large.
		var width = 0;
		var menu = $("#sub_nav menu div");
		var extras = $("<div>");
		$("#sub_nav > a").each(function() {
			var iwidth = $(this).width() + 29;
			if (width + iwidth > 910) {
				extras.append($(this));
			}
			width += iwidth;
		});
		if (width > 910) {
			$("#sub_nav menu").show().find("div").prepend(extras.html());
		}
	</script>
	<?
		}

		echo $bigtree["content"];
	?>
</div>
<?
	// Send JSON if we're doing a partial.
	if ($_SERVER["HTTP_BIGTREE_PARTIAL"]) {
		header("Content-type: text/json");
		$site = $cms->getPage(0,false);
		$title = $bigtree["admin_title"] ? $bigtree["admin_title"]." | ".$site["nav_title"]." Admin" : $site["nav_title"]." Admin";
		if (is_array($bigtree["js"])) {
			foreach ($bigtree["js"] as &$script) {
				$script = ADMIN_ROOT."js/$script";
			}
		}
		if (is_array($bigtree["css"])) {
			foreach ($bigtree["css"] as &$style) {
				$style = ADMIN_ROOT."css/$style";
			}
		}
		echo json_encode(array(
			"breadcrumb" => $breadcrumb,
			"title" => $title,
			"page" => ob_get_clean(),
			"active_nav" => $bigtree["active_nav_item"],
			"scripts" => $bigtree["js"],
			"css" => $bigtree["css"]
		));
	// Otherwise include the footer
	} else {
		include BigTree::path("admin/layouts/_footer.php");
	}
?>
