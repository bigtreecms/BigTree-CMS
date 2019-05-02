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
<hr>

<h3><?=Text::translate("Thumbnail / Poster Image Options")?></h3>

<fieldset>
	<label for="settings_field_directory"><?=Text::translate("Upload Directory <small>(relative to SITE_ROOT)</small>")?></label>
	<input id="settings_field_directory" type="text" name="directory" value="<?=Text::htmlEncode($settings["directory"])?>" />
</fieldset>

<?php
	// Just use the regular image options
	$image_options_prefix = null;
	include Router::getIncludePath("admin/field-types/_image-options.php");
