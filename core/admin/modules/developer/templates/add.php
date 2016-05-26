<?php
	namespace BigTree;
	
	// Clear out notices
	$name = $description = $callouts_enabled = $level = $module = $image = "";
	$resources = array();
	$show_error = false;
	
	if ($_SESSION["bigtree_admin"]["error"]) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["saved"]);
		$show_error = $_SESSION["bigtree_admin"]["error"];
		unset($_SESSION["bigtree_admin"]["error"]);
		unset($_SESSION["bigtree_admin"]["saved"]);
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>templates/create/" enctype="multipart/form-data" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/templates/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/templates/_common-js.php") ?>