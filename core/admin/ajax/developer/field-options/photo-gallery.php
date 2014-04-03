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
	$data["min_width"] = isset($data["min_width"]) ? $data["min_width"] : "";
	$data["min_height"] = isset($data["min_height"]) ? $data["min_height"] : "";
	$data["preview_prefix"] = isset($data["preview_prefix"]) ? $data["preview_prefix"] : "";
	$data["crops"] = isset($data["crops"]) ? $data["crops"] : "";
	$data["thumbs"] = isset($data["thumbs"]) ? $data["thumbs"] : "";
?>
<fieldset>
	<label>Upload Directory <small>(required, relative to SITE_ROOT)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($data["directory"])?>" class="required" />
</fieldset>
<fieldset>
	<input type="checkbox" name="disable_captions" <? if ($data["disable_captions"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Disable Captions</label>
</fieldset>
<? include BigTree::path("admin/ajax/developer/field-options/_image-options.php") ?>