<?php
	namespace BigTree;
	
	$pages = Page::search($_POST["query"], ["nav_title", "title"]);
	$resources = Resource::search($_POST["query"]);

	if (count($pages)) {
?>
<div class="link_field_results_header"><?=Text::translate("Pages")?></div>
<?php
		foreach ($pages as $page) {
			if ($page->Parent > 0) {
				$text = $page->ParentPage->NavTitle."&nbsp;&raquo;&nbsp;".$page->NavTitle;
			} else {
				$text = $page->NavTitle;
			}
?>
<a class="link_field_result" href="<?=Link::byPath($page->Path)?>" data-placeholder="<?=Text::translate("Page:", true)?> <?=$text?>"><?=$text?></a>
<?php
		}
	}

	if (count($resources["resources"])) {
?>
<div class="link_field_results_header">Files</div>
<?php
		foreach ($resources["resources"] as $resource) {
?>
<a class="link_field_result" href="<?=str_ireplace("{staticroot}", STATIC_ROOT, $resource["file"])?>" data-placeholder="<?=Text::translate("File:", true)?> <?=$resource["name"]?>"><?=$resource["name"]?></a>
<?php
		}
	}

	if (!count($pages) && !count($resources["resources"])) {
?>
<p class="link_field_no_results"><?=Text::translate("No Search Results")?></p>
<?php
	}
?>