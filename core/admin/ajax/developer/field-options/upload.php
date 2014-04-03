<?
	// Stop notices
	if (empty($data["directory"])) {
		if (isset($_POST["template"])) {
			$data["directory"] = "files/pages/";
		} elseif (isset($_POST["callout"])) {
			$data["directory"] = "files/callouts/";
		} elseif (isset($_POST["setting"])) {
			$data["directory"] = "files/settings/";
		} else {
			$data["directory"] = "files/modules/";
		}
	}
	$data["image"] = isset($data["image"]) ? $data["image"] : "";
?>
<fieldset>
	<label>Upload Directory <small>(required, relative to SITE_ROOT)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($data["directory"])?>" class="required" />
</fieldset>
<fieldset>
	<input type="checkbox" name="image"<? if ($data["image"]) { ?> checked="checked"<? } ?> id="image_uploader_enabled" />
	<label class="for_checkbox">Image Uploader Enabled <small>(enables crops, thumbs, preview)</small></label>
</fieldset>

<div id="image_uploader_options"<? if (!$data["image"]) { ?> style="display: none;"<? } ?>>
	<h4>Image Options</h4>
	<? include BigTree::path("admin/ajax/developer/field-options/_image-options.php") ?>
</div>

<script>
	$("#image_uploader_enabled").click(function() {
		$("#image_uploader_options").toggle();
	});
</script>