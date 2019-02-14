<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
	$sbp = isset($settings["simple_by_permission"]) ? $settings["simple_by_permission"] : "";
	
	if (isset($_POST["template"])) {
?>
<fieldset>
	<input id="settings_field_seo_body" type="checkbox" name="seo_body"<?php if (!empty($settings["seo_body"])) { ?> checked="checked"<?php } ?> />
	<label for="settings_field_seo_body" class="for_checkbox"><?=Text::translate("Use For Body Copy SEO Score")?></label>
</fieldset>
<?php
	}
?>

<fieldset>
	<input id="settings_field_simple" type="checkbox" name="simple"<?php if (!empty($settings["simple"])) { ?> checked="checked"<?php } ?> />
	<label for="settings_field_simple" class="for_checkbox"><?=Text::translate("Simple Mode <small>(less options)</small>")?></label>
</fieldset>

<hr />

<fieldset>
	<label for="settings_field_simple_permissions"><?=Text::translate("Simple Mode Via Permissions <small>(minimum access level)</small>")?></label>
	<select id="settings_field_simple_permissions" name="simple_by_permission">
		<option value="0"></option>
		<option value="1"<?php if ($sbp == "1") { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
		<option value="2"<?php if ($sbp == "2") { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
	</select>
</fieldset>

<p class="note"><?=Text::translate("If a user is below this permission level, this HTML area will switch to Simple Mode.")?></p>