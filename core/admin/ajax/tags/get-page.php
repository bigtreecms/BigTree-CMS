<?php
	$admin->requireLevel(1);
	
	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
	$sort_column = $_GET["sort"];
	$sort_direction = $_GET["sort_dir"];

	if ($sort_column != "tag" && $sort_column != "usage_count") {
		$sort_column = "tag";
	}

	if ($sort_direction != "ASC" && $sort_direction != "DESC") {
		$sort_direction = "ASC";
	}

	$pages = $admin->getTagsPageCount($query);
	$results = $admin->getPageOfTags($page, $query, $sort_column, $sort_direction);
	
	foreach ($results as $item) {
?>
<li>
	<section class="tag_name"><?=$item["tag"]?></section>
	<section class="tag_relationships"><?=$item["usage_count"]?></section>
	<section class="view_action view_action_merge"><a href="<?=ADMIN_ROOT?>tags/merge/<?=$item["id"]?>/" class="icon_merge"></a></section>
	<section class="view_action"><a href="#" data-id="<?=$item["id"]?>" class="icon_delete"></a></section>
</li>
<?php
	}
?>
<script>
	BigTree.setPageCount("#view_paging", <?=$pages?>, <?=$page?>);
</script>