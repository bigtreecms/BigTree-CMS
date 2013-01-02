<?
	$template = $cms->getTemplate(end($bigtree["path"]));
	BigTree::globalizeArray($template);
	$show_error = false;
?>
<div class="container">
	<form method="post" action="<?=$section_root?>update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$template["id"]?>" />
		<? include BigTree::path("admin/modules/developer/templates/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/templates/_common-js.php") ?>
<script>
	var resource_count = <?=$x?>;
</script>