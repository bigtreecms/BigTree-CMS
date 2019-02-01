<?php
	// Stop notices
	$settings["custom_value"] = isset($settings["custom_value"]) ? $settings["custom_value"] : "";
	$settings["default_checked"] = isset($settings["default_checked"]) ? $settings["default_checked"] : "";
?>
<fieldset>
	<input id="settings_field_default_on" name="default_checked" type="checkbox"<?php if ($settings["default_checked"]) { ?> checked<?php } ?> />
	<label for="settings_field_default_on" class="for_checkbox">Default to Checked</label>
</fieldset>

<fieldset>
	<label for="settings_field_value">Value <small>(defaults to "on")</small></label>
	<input id="settings_field_value" type="text" name="custom_value" value="<?=htmlspecialchars($settings["custom_value"])?>" />
</fieldset>