<?php
	namespace BigTree;
	
	if ($_SESSION["bigtree_admin"]["error"]) {
		$template = new Template($_SESSION["bigtree_admin"]["saved"]);
	} else {
		$template = new Template;
	}
	
	$form_action = "add";
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>templates/create/" enctype="multipart/form-data" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/templates/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<?php
	include Router::getIncludePath("admin/modules/developer/templates/_common-js.php");
	
	unset($_SESSION["bigtree_admin"]["saved"]);
	unset($_SESSION["bigtree_admin"]["error"]);
?>