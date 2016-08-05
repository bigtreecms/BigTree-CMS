<?php
	namespace BigTree;
	
	if ($_SESSION["bigtree_admin"]["error"]) {
		$field_type = new FieldType($_SESSION["bigtree_admin"]["saved"]);
		$show_error = $_SESSION["bigtree_admin"]["error"];
		
		unset($_SESSION["bigtree_admin"]["error"]);
		unset($_SESSION["bigtree_admin"]["saved"]);
	} else {
		$field_type = new FieldType;
		$field_type->UseCases = array("templates" => "on", "modules" => "on","callouts" => "on","settings" => "on");
		$show_error = false;
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>field-types/create/" enctype="multipart/form-data" class="module">
		<section>
			<div class="contain">
				<div class="left">
					<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
						<label for="fieldtype_field_id" class="required"><?=Text::translate('ID <small>(used for file name, alphanumeric, "-" and "_" only)</small>')?><?php if ($show_error) { ?> <span class="form_error_reason"><?=Text::translate($show_error)?></span><?php } ?></label>
						<input id="fieldtype_field_id" type="text" class="required" name="id" value="<?=Text::htmlEncode($field_type->ID)?>" />
					</fieldset>
					<fieldset>
						<label for="fieldtype_field_name" class="required"><?=Text::translate("Name")?></label>
						<input id="fieldtype_field_name" type="text" class="required" name="name" value="<?=Text::htmlEncode($field_type->Name)?>" />
					</fieldset>
				</div>
				<div class="right">
					<fieldset>
						<label class="required"><?=Text::translate("Use Cases")?></label>
						<ul class="developer_field_types_usage">
							<li><input id="fieldtype_field_use_case_templates" type="checkbox" name="use_cases[templates]"<?php if ($field_type->UseCases["templates"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_templates" class="for_checkbox"><?=Text::translate("Templates")?></label></li>
							<li><input id="fieldtype_field_use_case_modules" type="checkbox" name="use_cases[modules]"<?php if ($field_type->UseCases["modules"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_modules" class="for_checkbox"><?=Text::translate("Modules")?></label></li>
							<li><input id="fieldtype_field_use_case_callouts" type="checkbox" name="use_cases[callouts]"<?php if ($field_type->UseCases["callouts"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_callouts" class="for_checkbox"><?=Text::translate("Callouts")?></label></li>
							<li><input id="fieldtype_field_use_case_settings" type="checkbox" name="use_cases[settings]"<?php if ($field_type->UseCases["settings"]) { ?> checked="checked"<?php } ?> /> <label for="fieldtype_field_use_case_settings" class="for_checkbox"><?=Text::translate("Settings")?></label></li>
						</ul>
					</fieldset>
				</div>
			</div>
			<hr />
			<fieldset>
				<input id="fieldtype_field_self_draw" type="checkbox" name="self_draw"<?php if ($field_type->SelfDraw) { ?> checked="checked"<?php } ?> />
				<label for="fieldtype_field_self_draw" class="for_checkbox"><?=Text::translate('Self Draw <small>(if checked, you will need to draw your &lt;fieldset&gt; and &lt;label&gt; manually)</small>')?></label>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<script>
	BigTreeFormValidator("form.module");
</script>