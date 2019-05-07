<?php
	namespace BigTree;
	
	$forms = ModuleInterface::allByModuleAndType(null, "form", "title ASC");
?>
<section class="inset_block">
	<p><?=Text::translate("<strong>Content Generator</strong> allows you to create dummy content for your module data, aiding in testing your designs, search, and pagination.")?></p>
</section>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>content-generator/generate/">
		<section>
			<fieldset>
				<label for="generator_field_form"><?=Text::translate('Module Form <small>(populates test content into the table for this form)</small>')?></label>
				<select id="generator_field_form" name="form">
					<?php foreach ($forms as $form) { ?>
					<option value="<?=$form->ID?>"><?=$form->Title?> &mdash; <?=$form->Table?></option>
					<?php } ?>
				</select>
			</fieldset>
			<fieldset>
				<label for="generator_field_count"><?=Text::translate('Number of Entries to Create <small>(defaults to 25)</small>')?></label>
				<input id="generator_field_count" type="text" name="count" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" value="<?=Text::translate("Submit", true)?>" class="button blue" />
		</footer>
	</form>
</div>