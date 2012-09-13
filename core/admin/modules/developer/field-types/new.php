<?
	$breadcrumb[] = array("title" => "Created Field Type", "link" => "#");
	$file = end($bigtree["path"]).".php";
?>
<h1><span class="field_types"></span>Field Type Created</h1>
<? include BigTree::path("admin/modules/developer/field-types/_nav.php") ?>
<div class="form_container">
	<section>
		<p>Your new field type is setup and ready to use.</p>
		<ul class="styled clear">
			<li><?=SERVER_ROOT?>custom/admin/form-field-types/draw/<?=$file?> &mdash; Your drawing file.</li>
			<li><?=SERVER_ROOT?>custom/admin/form-field-types/process/<?=$file?> &mdash; Your processing file.</li>
			<li><?=SERVER_ROOT?>custom/admin/ajax/developer/field-options/<?=$file?> &mdash; Your field options file.</li>
		</ul>
	</section>
</div>

