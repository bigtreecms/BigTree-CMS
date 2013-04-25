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
	<section class="view_column users_name"><span class="gravatar"><img src="<?=BigTree::gravatar($item["email"], 36)?>" alt="" /></span><?=$item["name"]?></section>
	<section class="view_column users_email"><?=$item["email"]?></section>
	<section class="view_column users_company"><?=$item["company"]?></section>
	<section class="view_action">
		<? if ($admin->Level >= $item["level"]) { ?>
		<a href="<?=ADMIN_ROOT?>users/edit/<?=$item["id"]?>/" class="icon_edit"></a>
		<? } else { ?>
		<span class="icon_edit disabled_icon has_tooltip" data-tooltip="<p><strong>Edit User</strong></p><p>You may not edit users with higher permission levels than you.</p>"></span>
		<? } ?>
	</section>
	<section class="view_action">
		<? if ($admin->ID == $item["id"]) { ?>
		<span class="icon_delete disabled_icon has_tooltip" data-tooltip="<p><strong>Delete User</strong></p><p>You may not delete yourself.</p>"></span>
		<? } elseif ($admin->Level >= $item["level"]) { ?>
		<a href="#<?=$item["id"]?>" class="icon_delete"></a>
		<? } else { ?>
		<span class="icon_delete disabled_icon has_tooltip" data-tooltip="<p><strong>Delete User</strong></p><p>You may not delete users with higher permission levels than you.</p>"></span>
		<? } ?>
	</section>
</li>
<?
	}
?>
<script>
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>