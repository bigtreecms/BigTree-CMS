<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["seo_body"] = isset($options["seo_body"]) ? $options["seo_body"] : "";
	$options["simple"] = isset($options["simple"]) ? $options["simple"] : "";
	$sbp = isset($options["simple_by_permission"]) ? $options["simple_by_permission"] : "";
	
	if (isset($_POST["template"])) {
?>
<fieldset>
	<input id="options_field_seo" type="checkbox" name="seo_body"<?php if ($options["seo_body"]) { ?> checked="checked"<?php } ?> />
	<label for="options_field_seo" class="for_checkbox"><?=Text::translate("Use For Body Copy SEO Score")?></label>
</fieldset>
<?php
	}
?>
<fieldset>
	<input id="options_field_simple" type="checkbox" name="simple"<?php if ($options["simple"]) { ?> checked="checked"<?php } ?> />
	<label for="options_field_simple" class="for_checkbox"><?=Text::translate("Simple Mode <small>(less options)</small>")?></label>
</fieldset>

<hr />

<fieldset>
	<label for="options_field_level"><?=Text::translate("Simple Mode Via Permissions <small>(minimum access level)</small>")?></label>
	<select id="options_field_level" name="simple_by_permission">
		<option value="0"></option>
		<option value="1"<?php if ($sbp == "1") { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
		<option value="2"<?php if ($sbp == "2") { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
	</select>
</fieldset>

<p class="note"><?=Text::translate("If a user is below this permission level, this HTML area will switch to Simple Mode.")?></p>
