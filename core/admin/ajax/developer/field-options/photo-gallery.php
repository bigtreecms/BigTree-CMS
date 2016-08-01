<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	if (empty($options["directory"])) {
		if (isset($_POST["template"])) {
			$options["directory"] = "files/pages/";
		} elseif (isset($_POST["callout"])) {
			$options["directory"] = "files/callouts/";
		} elseif (isset($_POST["setting"])) {
			$options["directory"] = "files/settings/";
		} else {
			$options["directory"] = "files/modules/";
		}
	}
	
	$options["image"] = isset($options["image"]) ? $options["image"] : "";
	$options["min_width"] = isset($options["min_width"]) ? $options["min_width"] : "";
	$options["min_height"] = isset($options["min_height"]) ? $options["min_height"] : "";
	$options["preview_prefix"] = isset($options["preview_prefix"]) ? $options["preview_prefix"] : "";
	$options["crops"] = isset($options["crops"]) ? $options["crops"] : "";
	$options["thumbs"] = isset($options["thumbs"]) ? $options["thumbs"] : "";
?>
<fieldset>
	<label for="options_field_directory"><?=Text::translate("Upload Directory <small>(required, relative to SITE_ROOT)</small>")?></label>
	<input id="options_field_directory" type="text" name="directory" value="<?=htmlspecialchars($options["directory"])?>" class="required" />
</fieldset>
<fieldset>
	<input id="options_field_captions" type="checkbox" name="disable_captions" <?php if ($options["disable_captions"]) { ?>checked="checked" <?php } ?>/>
	<label for="options_field_captions" class="for_checkbox"><?=Text::translate("Disable Captions")?></label>
</fieldset>
<?php include Router::getIncludePath("admin/ajax/developer/field-options/_image-options.php") ?>