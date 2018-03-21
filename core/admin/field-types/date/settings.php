<?php
	// Stop notices
	$settings["default_today"] = isset($settings["default_today"]) ? $settings["default_today"] : "";
?>
<fieldset>
	<input id="settings_field_default_today" type="checkbox" name="default_today"<?php if ($settings["default_today"]) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_default_today" class="for_checkbox">Default to Today's Date</label>
</fieldset>