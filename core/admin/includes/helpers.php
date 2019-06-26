<?php
	namespace BigTree;
	
	function include_with(string $path, array $variables = []): void {
		foreach ($variables as $key => $value) {
			$$key = $value;
		}
		
		if (file_exists($path)) {
			include $path;
		} else {
			include Router::getIncludePath("layouts/partials/".$path.".php");
		}
	}
	
	function icon(string $class, string $name): void {
?>
<span class="<?=$class?>_icon">
	<svg class="icon icon_<?=$name?>">
		<use xlink:href="<?=ADMIN_ROOT?>images/icons.svg#<?=$name?>"></use>
	</svg>
</span>
<?php
	}
	
	function button(string $class, string $title, string $url, string $icon): void {
?>
<a class="<?=$class?>_button" href="<?=$url?>">
	<span class="<?=$class?>_button_inner">
		<span class="<?=$class?>_button_icon"><?php icon($class."_button", $icon) ?></span>
		<span class="<?=$class?>_button_label"><?=Text::translate($title)?></span>
	</span>
</a>
<?php
	}

	function get_navigation_menu_state(): string {
		$menu = [];
		
		foreach (Router::$AdminNavTree as $item) {
			if ($item["hidden"]) {
				continue;
			}
			
			if (empty($item["level"])) {
				$item["level"] = 0;
			}
			
			if (Auth::user()->Level >= $item["level"] && (!Auth::$PagesTabHidden || $item["link"] != "pages")) {
				// Need to check custom nav states better
				$link_pieces = explode("/", $item["link"]);
				$path_pieces = array_slice(Router::$Path, 1, count($link_pieces));
				
				if (strpos($item["link"], "https://") === 0 || strpos($item["link"], "http://") === 0) {
					$link = $item["link"];
				} else {
					$link = $item["link"] ? ADMIN_ROOT.$item["link"]."/" : ADMIN_ROOT;
				}
				
				$active = ($link_pieces == $path_pieces || ($item["link"] == "modules" && isset($bigtree["module"])));
				
				$menu_link = [
					"title" => $item["title"],
					"url" => $link,
					"icon" => $item["icon"],
					"active" => $active,
					"children" => []
				];
				
				if ($active && empty($item["no_top_level_children"]) && isset($item["children"]) && count($item["children"])) {
					foreach ($item["children"] as $child) {
						if (!empty($child["top_level_hidden"])) {
							continue;
						}
						
						if (strpos($child["link"], "https://") === 0 || strpos($child["link"], "http://") === 0) {
							$child_link = $child["link"];
						} else {
							$child_link = $child["link"] ? ADMIN_ROOT.rtrim($child["link"], "/")."/" : ADMIN_ROOT;
						}
						
						if (Auth::user()->Level >= $child["access"]) {
							$menu_link["children"][] = [
								"title" => $child["title"],
								"url" => $child_link
							];
						}
					}
				}
				
				$menu[] = $menu_link;
			}
		}
		
		return JSON::encode($menu);
	}
	