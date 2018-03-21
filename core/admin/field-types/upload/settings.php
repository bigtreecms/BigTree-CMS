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
?>
<fieldset>
	<label for="settings_field_directory">Upload Directory <small>(required, relative to SITE_ROOT)</small></label>
	<input id="settings_field_directory" type="text" name="directory" value="<?=htmlspecialchars($settings["directory"])?>" class="required" />
</fieldset>
<fieldset>
	<input id="image_uploader_enabled" type="checkbox" name="image"<?php if ($settings["image"]) { ?> checked="checked"<?php } ?> />
	<label for="image_uploader_enabled" class="for_checkbox">Image Uploader Enabled <small>(enables crops, thumbs, preview)</small></label>
</fieldset>

<div id="image_uploader_options"<?php if (!$settings["image"]) { ?> style="display: none;"<?php } ?>>
	<h4>Image Options</h4>
	<?php include BigTree::path("admin/field-types/_image-options.php"); ?>
</div>

<script>
	$("#image_uploader_enabled").click(function() {
		$("#image_uploader_options").toggle();
	});
</script>