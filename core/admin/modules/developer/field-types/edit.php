<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	$field_type = new FieldType(end(Router::$Commands));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>field-types/update/" enctype="multipart/form-data" class="module">
	  <?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="id" value="<?=$field_type->ID?>" />
		<section>
			<fieldset>
				<label for="fieldtype_field_name" class="required"><?=Text::translate("Name")?></label>
				<input id="fieldtype_field_name" type="text" class="required" name="name" value="<?=$field_type->Name?>" />
			</fieldset>
			<fieldset>
				<label class="required"><?=Text::translate("Use Cases")?></label>
				<ul class="developer_field_types_usage">
					<li><input id="fieldtype_field_use_case_templates" type="checkbox" name="use_cases[templates]"<?php if ($field_type->UseCases["templates"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_templates" class="for_checkbox"><?=Text::translate("Templates")?></label></li>
					<li><input id="fieldtype_field_use_case_modules" type="checkbox" name="use_cases[modules]"<?php if ($field_type->UseCases["modules"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_modules" class="for_checkbox"><?=Text::translate("Modules")?></label></li>
					<li><input id="fieldtype_field_use_case_callouts" type="checkbox" name="use_cases[callouts]"<?php if ($field_type->UseCases["callouts"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_callouts" class="for_checkbox"><?=Text::translate("Callouts")?></label></li>
					<li><input id="fieldtype_field_use_case_settings" type="checkbox" name="use_cases[settings]"<?php if ($field_type->UseCases["settings"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_settings" class="for_checkbox"><?=Text::translate("Settings")?></label></li>
				</ul>
			</fieldset>
			<hr />
			<fieldset>
				<input id="fieldtype_field_self_draw" type="checkbox" name="self_draw"<?php if ($field_type->SelfDraw) { ?> checked="checked"<?php } ?> />
				<label for="fieldtype_field_self_draw" class="for_checkbox"><?=Text::translate('Self Draw <small>(if checked, you will need to draw your &lt;fieldset&gt; and &lt;label&gt; manually)</small>')?></label>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<script>
	BigTreeFormValidator("form.module");
</script>