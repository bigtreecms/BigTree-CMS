<?php
	namespace BigTree;
	
	$callout = new BigTree\Callout(end($bigtree["path"]));	
	BigTree::globalizeArray($callout->Array);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$callout->ID?>" />
		<?php Router::includeFile("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<?php Router::includeFile("admin/modules/developer/callouts/_common-js.php") ?>