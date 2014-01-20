<?	
	// Stop notices
	$id = $name = $description = $display_default = $level = "";
	$resources = array();
	$show_error = false;
	
	if ($_SESSION["bigtree_admin"]["admin_error"]) {
		unset($_SESSION["bigtree_admin"]["admin_error"]);
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["admin_saved"]);
		unset($_SESSION["bigtree_admin"]["admin_saved"]);
		$show_error = true;
	}
?>
<div class="container">
	<form method="post" action="<?=$section_root?>create/" enctype="multipart/form-data" class="module">
		<? include BigTree::path("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/callouts/_common-js.php") ?>
<script>
	BigTree.localResourceCount = <?=$x?>;
</script>