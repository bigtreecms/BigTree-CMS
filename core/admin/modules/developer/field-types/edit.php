<?
	$breadcrumb[] = array("title" => "Edit Field Type", "link" => "#");
	
	$type = $admin->getFieldType(end($commands));
	
	BigTree::globalizeArray($type,array("htmlspecialchars"));
?>
<h1><span class="icon_developer_field_types"></span>Edit Field Type</h1>
<? include BigTree::path("admin/modules/developer/field-types/_nav.php") ?>

<div class="form_container">
	<form method="post" action="<?=$developer_root?>field-types/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$id?>" />
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" class="required" name="name" value="<?=$name?>" />
			</fieldset>
			<fieldset>
				<label class="required">Use Cases</label>
				<ul class="developer_field_types_usage">
					<li><input type="checkbox" name="pages"<? if ($pages) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Pages</label></li>
					<li><input type="checkbox" name="modules"<? if ($modules) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Modules</label></li>
					<li><input type="checkbox" name="callouts"<? if ($callouts) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Callouts</label></li>
				</ul>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<script type="text/javascript">
	new BigTreeFormValidator("form.module");
</script>