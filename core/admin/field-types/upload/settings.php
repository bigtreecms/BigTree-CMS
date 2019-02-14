<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
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
	<label for="settings_field_directory"><?=Text::translate("Upload Directory <small>(required, relative to SITE_ROOT)</small>")?></label>
	<input id="settings_field_directory" type="text" name="directory" value="<?=Text::htmlEncode($settings["directory"])?>" class="required" />
</fieldset>

<fieldset>
	<label for="settings_field_extensions"><?=Text::translate("Valid File Extensions <small>(comma separated, include "." prefix for each extension)</small>")?></label>
	<textarea id="settings_field_extensions" name="valid_extensions" placeholder="<?=Text::translate("Leaving this field empty will allow all non-executable files to be uploaded.", true)?>"><?=Text::htmlEncode($settings["valid_extensions"])?></textarea>
</fieldset>