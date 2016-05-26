<?php
	namespace BigTree;
	
	$item = $admin->getSetting(end($bigtree["path"]));
	Globalize::arrayObject($item,array("htmlspecialchars"));
	
	if (isset($_SESSION["bigtree_admin"]["developer"]["setting_data"])) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
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
	<form class="module" method="post" action="<?=DEVELOPER_ROOT?>settings/update/<?=$item["id"]?>/">
		<?php include Router::getIncludePath("admin/modules/developer/settings/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
</script>
<?php
	$bigtree["html_fields"] = array("setting_description");
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
	
	unset($module);
?>