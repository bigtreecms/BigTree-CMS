<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (!empty($_SESSION["bigtree_admin"]["developer"]["setting_data"])) {
		$setting = new Setting($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
		$error = $_SESSION["bigtree_admin"]["developer"]["error"];
		
		unset($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
	} else {
		$setting = new Setting(end($bigtree["path"]));
	}
?>
<div class="container">
	<form class="module" method="post" action="<?=DEVELOPER_ROOT?>settings/update/<?=$setting->ID?>/">
		<?php include Router::getIncludePath("admin/modules/developer/settings/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>