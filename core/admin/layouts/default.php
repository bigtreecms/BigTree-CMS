<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$find_path = function($nav,$path,$last_link = "") use (&$find_path) {
		static $page = ["navigation" => [], "related" => []];

		foreach ($nav as $item) {
			if ((strpos($path,$item["link"]."/") === 0 && $item["link"] != $last_link) || $path == $item["link"]) {				
				$page["breadcrumb"][] = ["title" => $item["title"],"link" => $item["link"]];
				$page["title"] = $item["title"] ? $item["title"] : $page["title"];
				$page["title"] = $item["title_override"] ? $item["title_override"] : $page["title"];
				$page["icon"] = $item["icon"] ? $item["icon"] : $page["icon"];
				$page["navigation"] = $item["children"] ? $item["children"] : $page["navigation"];

				// Get the related dropdown menu
				if ($item["related"]) {
					$page["related"]["title"] = $page["title"];
					$page["related"]["nav"] = $page["navigation"];
				}

				if ($item["children"]) {
					return $find_path($item["children"],$path,$item["link"]);
				}
			}
		}

		return $page;
	};

	$current_path = implode("/", array_slice(Router::$Path, 1));

	if (!defined("BIGTREE_ACCESS_DENIED")) {
		$bigtree["page"] = $find_path(Router::$AdminNavTree, $current_path);
	}

	// Set the page title if it hasn't been set
	if (!$bigtree["admin_title"]) {
		$bigtree["admin_title"] = $bigtree["page"]["title"];
	}

	// If we're in a module, add "Modules" to the beginning of the breadcrumb
	if (defined("MODULE_ROOT")) {
		$bigtree["page"]["breadcrumb"] = array_merge(
			[["title" => "Modules", "link" => "modules"]],
			$bigtree["page"]["breadcrumb"]
		);
	}

	// Don't replace breadcrumb with a custom one if it exists
	if (!array_filter((array) $bigtree["breadcrumb"])) {
		$bigtree["breadcrumb"] = $bigtree["page"]["breadcrumb"];
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
		foreach ($bigtree["breadcrumb"] as &$item) {
			$item["title"] = Text::htmlEncode($item["title"]);
		}
		unset($item);
		// We're going to fake include the header to get the active nav state.
		include Router::getIncludePath("admin/layouts/_header.php");
		ob_clean();
	// Otherwise, full page render, so include the header and draw the breadcrumb.
	} else {
		include Router::getIncludePath("admin/layouts/_header.php");
	}
?>
<div id="page">
	<?php
		if ($bigtree["page"]["title"] && !defined("BIGTREE_404")) {
	?>
	<h1>
		<span class="page_icon <?=$bigtree["page"]["icon"]?>"><?php if ($bigtree["page"]["icon"] == "gravatar") { ?><img src="<?=User::gravatar($bigtree["gravatar"])?>" alt="" /><?php } ?></span>
		<?php
			$x = 0;
			foreach ($bigtree["breadcrumb"] as $item) {
				$x++;
				
		?>
		<a href="<?=ADMIN_ROOT.$item["link"]?>/" class="<?php if ($x == 1) { ?> first<?php } if ($x == count($bigtree["breadcrumb"])) { ?> last<?php } ?>"><?=Text::translate($item["title"], true)?></a>
		<?php
				if ($x != count($bigtree["breadcrumb"])) {
		?>
		<span class="divider">&rsaquo;</span>
		<?php
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
			<span class="icon"><?=Text::translate("Related")?></span>
			<nav class="dropdown">
				<strong><?=Text::translate($bigtree["page"]["related"]["title"])?></strong>
				<?php
					foreach ($bigtree["page"]["related"]["nav"] as $item) {
						if ($item["level"] <= Auth::user()->Level) {
				?>
				<a href="<?=ADMIN_ROOT.$item["link"]?>/"><?=Text::translate($item["title"])?></a>
				<?php
						}
					}
				?>
			</nav>
		</nav>
		<?php
			}
		?>
	</h1>
	<?php
		}
		
		// Allow for custom navigation
		if (!empty($bigtree["custom_subnav"])) {
			$bigtree["page"]["navigation"] = $bigtree["custom_subnav"];
			$show_nav = true;
		} else {
			$show_nav = false;
			
			foreach ($bigtree["page"]["navigation"] as $item) {
				if (!$item["hidden"] && empty($item["group"])) {
					$show_nav = true;
				}
			}
		}
		
		if ($show_nav && !defined("BIGTREE_404")) {
	?>
	<nav id="sub_nav">
		<?php
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
				if (!$item["hidden"] && (!$item["level"] || $item["level"] <= Auth::user()->Level)) {
					$get_string = "";

					if (is_array($item["get_vars"]) && count($item["get_vars"])) {
						$get_string = "?";
					
						foreach ($item["get_vars"] as $key => $val) {
							$get_string .= "$key=".urlencode($val)."&";
						}
					}
		?>
		<a href="<?=ADMIN_ROOT.$item["link"]?>/<?=htmlspecialchars(rtrim($get_string,"&"))?>"<?php if ($active_item == $item) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=($item["nav_icon"] ? $item["nav_icon"] : $item["icon"])?>"></span><?=Text::translate($item["title"])?></a>
		<?php
				}
			}
		?>
		<menu<?php if (!count($bigtree["subnav_extras"])) { ?> style="display: none;"<?php } ?>>
			<span class="icon"></span>
			<div>
				<?php
					if (is_array($bigtree["subnav_extras"])) {
						foreach ($bigtree["subnav_extras"] as $link) {
							if (Auth::user()->Level >= $link["level"]) {
				?>
				<a href="<?=$link["link"]?>"><span class="icon_small icon_small_<?=$link["icon"]?>"></span><?=Text::translate($link["title"])?></a>
				<?php
							}
						}
					}
				?>
			</div>
		</menu>
	</nav>
	<script>
		// Placing this here inline because we want the menu rendering changed on page render if it's too large.
		(function() {
			var width = 0;
			var menu = $("#sub_nav").find("menu div");
			var extras = $("<div>");
			
			$("#sub_nav > a").each(function () {
				var iwidth = $(this).width() + 29;
				if (width + iwidth > 910) {
					extras.append($(this));
				}
				width += iwidth;
			});
			
			if (width > 910) {
				$("#sub_nav").find("menu").show().find("div").prepend(extras.html());
			}
		})();
	</script>
	<?php
		}

		Router::renderContent();
	?>
</div>
<?php
	// Send JSON if we're doing a partial.
	if ($_SERVER["HTTP_BIGTREE_PARTIAL"]) {
		header("Content-type: text/json");
		$site = new Page(0, false);
		$title = $bigtree["admin_title"] ? $bigtree["admin_title"]." | ".$site->NavigationTitle." Admin" : $site->NavigationTitle." Admin";
		
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
		
		echo json_encode([
			"breadcrumb" => $bigtree["breadcrumb"],
			"title" => $title,
			"page" => ob_get_clean(),
			"active_nav" => $bigtree["active_nav_item"],
			"scripts" => $bigtree["js"],
			"css" => $bigtree["css"]
		]);
	// Otherwise include the footer
	} else {
		include Router::getIncludePath("admin/layouts/_footer.php");
	}
