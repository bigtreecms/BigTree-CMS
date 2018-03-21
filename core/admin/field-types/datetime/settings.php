<?php
	// Stop notices
	$settings["default_now"] = isset($settings["default_now"]) ? $settings["default_now"] : "";
?>
<fieldset>
	<input id="settings_field_default_now" type="checkbox" name="default_now"<?php if ($settings["default_now"]) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_default_now" class="for_checkbox">Default to Today's Date &amp; Time</label>
</fieldset>