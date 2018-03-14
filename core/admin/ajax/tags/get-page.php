<?php
	$admin->requireLevel(1);
	
	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;

	$pages = $admin->getTagsPageCount($query);
	$results = $admin->getPageOfTags($page, $query);
	
	foreach ($results as $item) {
?>
<li>
	<section class="tag_name"><?=$item["tag"]?></section>
	<section class="tag_relationships"><?=$item["usage_count"]?></section>
	<section class="view_action view_action_merge"><a href="<?=ADMIN_ROOT?>tags/merge/<?=$item["id"]?>/" class="icon_merge"></a></section>
</li>
<?php
	}
?>
<script>
	BigTree.setPageCount("#view_paging", <?=$pages?>, <?=$page?>);
</script>