<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	$file = htmlspecialchars(end($bigtree["path"])).".php";
?>
<div class="container">
	<section>
		<p><?=Text::translate("Your new field type is setup and ready to use.")?></p>
		<ul>
			<li><?=SERVER_ROOT?>custom/admin/form-field-types/draw/<?=$file?> &mdash; <?=Text::translate("Your drawing file.")?></li>
			<li><?=SERVER_ROOT?>custom/admin/form-field-types/process/<?=$file?> &mdash; <?=Text::translate("Your processing file.")?></li>
			<li><?=SERVER_ROOT?>custom/admin/ajax/developer/field-options/<?=$file?> &mdash; <?=Text::translate("Your field options file.")?></li>
		</ul>
	</section>
</div>

