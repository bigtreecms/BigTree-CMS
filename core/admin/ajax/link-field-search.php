<?php
	namespace BigTree;
	
	$pages = Page::search($_POST["query"], ["nav_title", "title"]);
	$resources = Resource::search($_POST["query"]);

	if (count($pages)) {
?>
<div class="field_link_results_header"><?=Text::translate("Pages")?></div>
<?php
		foreach ($pages as $page) {
			if ($page->Parent > 0) {
				$text = $page->ParentPage->NavigationTitle."&nbsp;&raquo;&nbsp;".$page->NavigationTitle;
			} else {
				$text = $page->NavigationTitle;
			}
?>
<a class="field_link_result" href="<?=Link::get($page->ID)?>" data-placeholder="<?=Text::translate("Page:", true)?> <?=$text?>"><?=$text?></a>
<?php
		}
	}

	if (count($resources["resources"])) {
?>
<div class="field_link_results_header">Files</div>
<?php
		foreach ($resources["resources"] as $resource) {
?>
<a class="field_link_result" href="<?=str_ireplace("{staticroot}", STATIC_ROOT, $resource["file"])?>" data-placeholder="<?=Text::translate("File:", true)?> <?=$resource["name"]?>"><?=$resource["name"]?></a>
<?php
		}
	}

	if (!count($pages) && !count($resources["resources"])) {
?>
<p class="field_link_no_results"><?=Text::translate("No Search Results")?></p>
<?php
	}
?>