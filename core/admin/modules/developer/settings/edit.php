<?php
	$item = $admin->getSetting(end($bigtree["path"]));
	BigTree::globalizeArray($item,array("htmlspecialchars"));
	
	if (isset($_SESSION["bigtree_admin"]["developer"]["setting_data"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
		unset($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
	}
	
	if (isset($_SESSION["bigtree_admin"]["developer"]["error"])) {
		$e = $_SESSION["bigtree_admin"]["developer"]["error"];
		unset($_SESSION["bigtree_admin"]["developer"]["error"]);
	} else {
		$e = false;
	}
?>
<div class="container">
	<div class="developer_buttons">
		<a href="<?=ADMIN_ROOT?>settings/edit/<?=$item["id"]?>/" title="Edit Setting Value">
			Edit Setting Value
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
	</div>

	<form class="module" method="post" action="<?=DEVELOPER_ROOT?>settings/update/<?=$item["id"]?>/">
		<?php include BigTree::path("admin/modules/developer/settings/_form-content.php"); ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
</script>
<?php
	$bigtree["html_fields"] = array("setting_description");
	include BigTree::path("admin/layouts/_html-field-loader.php");
	
	unset($module);
?>