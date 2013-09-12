<?
	$admin->requireLevel(1);
	
	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? $_GET["page"] : 1;
	$sort_by = isset($_GET["sort"]) ? $_GET["sort"] : "name";
	$sort_dir = isset($_GET["sort_direction"]) ? $_GET["sort_direction"] : "ASC";

	$pages = $admin->getUsersPageCount($query);
	$results = $admin->getPageOfUsers($page,$query,"`$sort_by` $sort_dir");
	
	foreach ($results as $item) {
?>
<li id="row_<?=$item["id"]?>">
	<section class="view_column users_name_emulate"><span class="gravatar"><img src="<?=BigTree::gravatar($item["email"], 36)?>" alt="" /></span><?=$item["name"]?></section>
	<section class="view_column users_email"><?=$item["email"]?></section>
	<section class="view_column users_company"><?=$item["company"]?></section>
	<section class="view_action">
		<a href="<?=ADMIN_ROOT?>developer/user-emulator/emulate/<?=$item["id"]?>/" class="icon_settings ignore_quick_loader"></a>
	</section>
</li>
<?
	}
?>
<script>
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>