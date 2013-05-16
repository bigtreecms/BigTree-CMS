<?
	$file = end($bigtree["path"]).".php";
?>
<div class="container">
	<section>
		<p>Your new field type is setup and ready to use.</p>
		<ul>
			<li><?=SERVER_ROOT?>custom/admin/form-field-types/draw/<?=$file?> &mdash; Your drawing file.</li>
			<li><?=SERVER_ROOT?>custom/admin/form-field-types/process/<?=$file?> &mdash; Your processing file.</li>
			<li><?=SERVER_ROOT?>custom/admin/ajax/developer/field-options/<?=$file?> &mdash; Your field options file.</li>
		</ul>
	</section>
</div>

