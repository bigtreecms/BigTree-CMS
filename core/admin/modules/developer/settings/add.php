<?php
	namespace BigTree;
	
	if (!empty($_SESSION["bigtree_admin"]["developer"]["setting_data"])) {
		$setting = new Setting($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
		$error = $_SESSION["bigtree_admin"]["developer"]["error"];

		unset($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
	} else {
		$setting = new Setting;
		$error = false;
	}
?>
<div class="container">
	<form class="module" method="post" action="<?=DEVELOPER_ROOT?>settings/create/">
		<?php include Router::getIncludePath("admin/modules/developer/settings/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>