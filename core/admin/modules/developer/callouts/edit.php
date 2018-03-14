<?php
	$callout = $admin->getCallout(end($bigtree["path"]));	
	BigTree::globalizeArray($callout);
	
	$resources = $callout["resources"];
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$callout["id"]?>" />
		<?php include BigTree::path("admin/modules/developer/callouts/_form-content.php"); ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<?php include BigTree::path("admin/modules/developer/callouts/_common-js.php"); ?>
<script>
	BigTree.localResourceCount = <?=$x?>;
</script>