<?
	$callout = $cms->getCallout(end($bigtree["path"]));	
	BigTree::globalizeArray($callout);
	
	$resources = json_decode($callout["resources"],true);
?>
<div class="container">
	<form method="post" action="<?=$section_root?>update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$callout["id"]?>" />
		<? include BigTree::path("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/callouts/_common-js.php") ?>
<script>
	var resource_count = <?=$x?>;
</script>