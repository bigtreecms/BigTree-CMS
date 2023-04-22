<?php
	// If we have a decent sized search query, bring in a wider set of results, otherwise don't search for a huge amount of things
	if (strlen($_POST["query"]) > 2) {
		$pages = $admin->searchPages($_POST["query"], ["nav_title"], 50);
		$resources = $admin->searchResources($_POST["query"], "date DESC", 50);
	} else {
		$pages = $admin->searchPages($_POST["query"], ["nav_title"], 10);
		$resources = $admin->searchResources($_POST["query"], "date DESC", 10);
	}

	if (count($pages)) {
?>
<div class="link_field_results_header">Pages</div>
<?php
		foreach ($pages as $page) {
			if ($page["parent"] > 0) {
				// Get the parent so we can provide some context to where this page lives
				$parent = $cms->getPage($page["parent"],false);
				$text = $parent["nav_title"]."&nbsp;&raquo;&nbsp;".$page["nav_title"];
			} else {
				$text = $page["nav_title"];
			}
?>
<a class="link_field_result" href="<?=$cms->getLink($page["id"])?>" data-placeholder="Page: <?=$text?>"><?=$text?></a>
<?php
		}
	}

	if (count($resources["resources"])) {
?>
<div class="link_field_results_header">Files</div>
<?php
		foreach ($resources["resources"] as $resource) {
?>
<a class="link_field_result" href="<?=str_ireplace("{staticroot}", STATIC_ROOT, $resource["file"])?>" data-placeholder="File: <?=$resource["name"]?>"><?=$resource["name"]?></a>
<?php
		}
	}

	if (!count($pages) && !count($resources["resources"])) {
?>
<p class="link_field_no_results">No Search Results</p>
<?php
	}
?>