<?php
	namespace BigTree;
	
	if ($_SESSION["bigtree_admin"]["error"]) {
		$callout = new Callout($_SESSION["bigtree_admin"]["saved"]);
		$show_error = $_SESSION["bigtree_admin"]["error"];
		
		unset($_SESSION["bigtree_admin"]["error"]);
		unset($_SESSION["bigtree_admin"]["saved"]);
	} else {
		$callout = new Callout;
		$show_error = "";
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/create/" enctype="multipart/form-data" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/callouts/_common-js.php") ?>