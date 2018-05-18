<?php
	$item = $cms->getFeed(end($bigtree["commands"]));
	
	BigTree::globalizeArray($item);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>feeds/update/<?=$id?>/" class="module">
		<?php include BigTree::path("admin/modules/developer/feeds/_form-content.php"); ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<?php include BigTree::path("admin/modules/developer/feeds/_common-js.php"); ?>