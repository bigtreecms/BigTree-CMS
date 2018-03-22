<?php
	// Stop notices
	$settings["max_length"] = isset($settings["max_length"]) ? $settings["max_length"] : "";
?>
<fieldset>
	<label for="settings_field_max_length">Maximum Character Length <small>(leave empty or 0 for no max)</small></label>
	<input id="settings_field_max_length" type="text" placeholder="0" name="max_length" value="<?=$settings["max_length"]?>" />
</fieldset>