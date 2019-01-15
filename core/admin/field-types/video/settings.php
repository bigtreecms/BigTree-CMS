<?php
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

<h3>Thumbnail / Poster Image Options</h3>

<fieldset>
	<label>Upload Directory <small>(relative to SITE_ROOT)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($settings["directory"])?>" />
</fieldset>

<?php
	// Just use the regular image options
	include BigTree::path("admin/field-types/_image-options.php");
