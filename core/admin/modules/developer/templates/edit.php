<?php
	namespace BigTree;
	
	$template = $cms->getTemplate(end($bigtree["path"]));
	Globalize::arrayObject($template);

	$show_error = false;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>templates/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$template["id"]?>" />
		<?php include Router::getIncludePath("admin/modules/developer/templates/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/templates/_common-js.php") ?>
<script>
	BigTree.localResourceCount = <?=$x?>;
</script>