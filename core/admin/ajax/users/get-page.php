<?
	$admin->requireLevel(1);
	
	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? $_GET["page"] : 0;

	$pages = $admin->getUsersPageCount($query);
	$results = $admin->getPageOfUsers($page,$query);
	
	foreach ($results as $item) {
?>
<li>
	<section class="users_name"><?=$item["name"]?></section>
	<section class="users_email"><?=$item["email"]?></section>
	<section class="users_company"><?=$item["company"]?></section>
	<section class="view_action">
		<? if ($admin->Level >= $item["level"]) { ?>
		<a href="<?=$admin_root?>users/edit/<?=$item["id"]?>/" class="icon_edit"></a>
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
<script type="text/javascript">
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>