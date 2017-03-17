<?
	$admin->requireLevel(1);
	
	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
	
	// Prevent SQL shenanigans
	$sort_by = "name";
	if (isset($_GET["sort"])) {
		$valid_columns = array("name","company","email");
		if (in_array($_GET["sort"],$valid_columns)) {
			$sort_by = $_GET["sort"];
		}
	}
	$sort_dir = (isset($_GET["sort_direction"]) && $_GET["sort_direction"] == "DESC") ? "DESC" : "ASC";

	$pages = $admin->getUsersPageCount($query);
	$results = $admin->getPageOfUsers($page,$query,"`$sort_by` $sort_dir");
	
	foreach ($results as $item) {
?>
<li id="row_<?=$item["id"]?>">
	<section class="view_column users_name_emulate"><span class="gravatar"><img src="<?=BigTree::gravatar($item["email"], 36)?>" alt="" /></span><?=$item["name"]?></section>
	<section class="view_column users_email"><?=$item["email"]?></section>
	<section class="view_column users_company"><?=$item["company"]?></section>
	<section class="view_action">
		<a href="<?=ADMIN_ROOT?>developer/user-emulator/emulate/?id=<?=$item["id"]?><? $admin->drawCSRFTokenGET() ?>" class="icon_settings ignore_quick_loader"></a>
	</section>
</li>
<?
	}
?>
<script>
	BigTree.setPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>