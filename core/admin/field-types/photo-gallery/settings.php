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
	$settings["image"] = isset($settings["image"]) ? $settings["image"] : "";
	$settings["min_width"] = isset($settings["min_width"]) ? $settings["min_width"] : "";
	$settings["min_height"] = isset($settings["min_height"]) ? $settings["min_height"] : "";
	$settings["preview_prefix"] = isset($settings["preview_prefix"]) ? $settings["preview_prefix"] : "";
	$settings["crops"] = isset($settings["crops"]) ? $settings["crops"] : "";
	$settings["thumbs"] = isset($settings["thumbs"]) ? $settings["thumbs"] : "";
?>
<fieldset>
	<label for="settings_field_directory">Upload Directory <small>(required, relative to SITE_ROOT)</small></label>
	<input id="settings_field_directory" type="text" name="directory" value="<?=htmlspecialchars($settings["directory"])?>" class="required" />
</fieldset>
<fieldset>
	<input id="settings_field_captions" type="checkbox" name="disable_captions" <?php if ($settings["disable_captions"]) { ?>checked="checked" <?php } ?>/>
	<label for="settings_field_captions" class="for_checkbox">Disable Captions</label>
</fieldset>
<?php include BigTree::path("admin/field-types/_image-options.php"); ?>