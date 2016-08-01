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
?>
<fieldset>
	<label for="options_field_directory"><?=Text::translate("Upload Directory <small>(required, relative to SITE_ROOT)</small>")?></label>
	<input id="options_field_directory" type="text" name="directory" value="<?=htmlspecialchars($options["directory"])?>" class="required" />
</fieldset>
<fieldset>
	<input id="options_field_image" type="checkbox" name="image"<?php if ($options["image"]) { ?> checked="checked"<?php } ?> id="image_uploader_enabled" />
	<label for="options_field_image" class="for_checkbox"><?=Text::translate("Image Uploader Enabled <small>(enables crops, thumbs, preview)</small>")?></label>
</fieldset>

<div id="image_uploader_options"<?php if (!$options["image"]) { ?> style="display: none;"<?php } ?>>
	<h4><?=Text::translate("Image Options")?></h4>
	<?php include Router::getIncludePath("admin/ajax/developer/field-options/_image-options.php") ?>
</div>

<script>
	$("#image_uploader_enabled").click(function() {
		$("#image_uploader_options").toggle();
	});
</script>