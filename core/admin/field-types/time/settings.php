<?php
	// Stop notices
	$settings["ignore_timezones"] = $settings["ignore_timezones"] ?? "";
?>
<fieldset>
	<input id="settings_field_ignore_timezones" type="checkbox" name="ignore_timezones"<?php if ($settings["ignore_timezones"]) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_ignore_timezones" class="for_checkbox">Ignore BigTree User Timezones</label>
</fieldset>