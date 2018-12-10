<?php
	// Stop notices
	if (empty($settings["directory"])) {
		if (isset($_POST["template"])) {
			$settings["directory"] = "files/pages/";
		} elseif (isset($_POST["callout"])) {
			$settings["directory"] = "files/callouts/";
		} elseif (isset($_POST["setting"])) {
			$settings["directory"] = "files/settings/";
		} else {
			$settings["directory"] = "files/modules/";
		}
	}
?>
<fieldset>
	<label for="settings_field_directory">Upload Directory <small>(required, relative to SITE_ROOT)</small></label>
	<input id="settings_field_directory" type="text" name="directory" value="<?=BigTree::safeEncode($settings["directory"])?>" class="required" />
</fieldset>

<fieldset>
	<label for="settings_field_extensions">Valid File Extensions <small>(comma separated, include "." prefix for each extension)</small></label>
	<textarea id="settings_field_extensions" name="valid_extensions" placeholder="Leaving this field empty will allow all non-executable files to be uploaded."><?=BigTree::safeEncode($settings["valid_extensions"])?></textarea>
</fieldset>