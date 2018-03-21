<?php
	// Stop notices
	$settings["custom_value"] = isset($settings["custom_value"]) ? $settings["custom_value"] : "";
?>
<fieldset>
	<label for="settings_field_value">Value <small>(defaults to "on")</small></label>
	<input id="settings_field_value" type="text" name="custom_value" value="<?=htmlspecialchars($settings["custom_value"])?>" />
</fieldset>