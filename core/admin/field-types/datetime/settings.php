<?php
	// Stop notices
	$settings["default_now"] = $settings["default_now"] ?? "";
	$settings["ignore_timezones"] = $settings["ignore_timezones"] ?? "";
?>
<fieldset>
	<input id="settings_field_default_now" type="checkbox" name="default_now"<?php if ($settings["default_now"]) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_default_now" class="for_checkbox">Default to Today's Date &amp; Time</label>
</fieldset>


<fieldset>
	<input id="settings_field_ignore_timezones" type="checkbox" name="ignore_timezones"<?php if ($settings["ignore_timezones"]) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_ignore_timezones" class="for_checkbox">Ignore BigTree User Timezones</label>
</fieldset>