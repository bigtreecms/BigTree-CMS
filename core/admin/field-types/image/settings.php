<?php
	namespace BigTree;

	// Set a default directory based on where this field is being used
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
<?php
	include Router::getIncludePath("admin/field-types/_image-options.php");
?>