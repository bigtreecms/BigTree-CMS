<?php
	$sub_children = $cms->getNavByParent($page["id"], 1);

	if ($page["parent"] == 0) {
		$sub_nav = $cms->getNavByParent($page["id"], 2);
		$top_level = $page;
	} else {
		$sub_nav = $cms->getNavByParent($page["parent"], 2);
		$top_level = $cms->getPage($page["parent"]);
	}

	if ((!array_filter($sub_children) || !array_filter($sub_nav)) && $page["parent"] != 0) {
		$parent = $cms->getPage($page["parent"]);

		if ($parent["parent"] != 0) {
			$sub_nav = $cms->getNavByParent($parent["parent"], 2);
			$top_level = $cms->getPage($parent["parent"]);
		}
	}

	$top_level["link"] = WWW_ROOT.$top_level["path"]."/";
	$current_url = BigTree::currentURL();
?>
<nav class="page_sidebar sub_nav">
	<span class="sub_nav_handle js-sub_navigation_handle">
		Navigation
	</span>
	<div class="sub_nav_wrapper js-navigation" data-navigation-handle=".js-sub_navigation_handle">
		<div class="sub_nav_item sub_nav_top">
			<a href="<?=$top_level["link"]?>" class="sub_nav_link"><?=$top_level["title"]?></a>
		</div>
		<?php
			foreach ($sub_nav as $item) {
				$has_children = is_array($item["children"]) && count($item["children"]);
				$is_active = (strpos($current_url, $item["link"]) !== false);
		?>
		<div class="sub_nav_item<?php if ($is_active) { ?> sub_nav_item_active<?php } ?><?php if ($is_active && $has_children) { ?> sub_nav_has_children<?php } ?>">
			<a href="<?=$item["link"]?>" class="sub_nav_link<?php if ($is_active) { ?> sub_nav_active<?php } ?>"<?php if ($item["new_window"]) { ?> target="_blank"<?php } ?>><?=$item["title"]?></a>
			<?php
				if ($is_active && $has_children) {
			?>
			<div class="sub_nav_children">
				<?php
					foreach ($item["children"] as $child) {
						$is_active = (strpos($current_url, $child["link"]) !== false);
				?>
				<div class="sub_nav_item">
					<a href="<?=$child["link"]?>" class="sub_nav_link<?php if ($is_active) { ?> sub_nav_active<?php } ?>"<?php if ($child["new_window"]) { ?> target="_blank"<?php } ?>><?=$child["title"]?></a>
				</div>
				<?php
					}
				?>
			</div>
			<?php
				}
			?>
		</div>
		<?php
			}
		?>
	</div>
</nav>