<?
	$breadcrumb[] = array("title" => "Edit Template", "link" => "#");
	$template = $cms->getTemplate(end($path));
	BigTree::globalizeArray($template);
?>
<h1><span class="icon_developer_templates"></span>Edit Template</h1>
<? include BigTree::path("admin/modules/developer/templates/_nav.php") ?>

<div class="form_container">
	<form method="post" action="<?=$section_root?>update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$template["id"]?>" />
		<? include BigTree::path("admin/modules/developer/templates/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/templates/_common-js.php") ?>
<script type="text/javascript">
	var resource_count = <?=$x?>;
</script>