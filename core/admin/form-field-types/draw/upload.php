<?
	// If we're using a preset, the prefix may be there
	if (!empty($field["options"]["preset"])) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = $cms->getSetting("bigtree-internal-media-settings");
		}
		$preset = $bigtree["media_settings"]["presets"][$field["options"]["preset"]];
		if (!empty($preset["preview_prefix"])) {
			$field["options"]["preview_prefix"] = $preset["preview_prefix"];
		}
		if (!empty($preset["min_width"])) {
			$field["options"]["min_width"] = $preset["min_width"];
		}
		if (!empty($preset["min_height"])) {
			$field["options"]["min_height"] = $preset["min_height"];
		}
	}

	// Get min width/height designations
	$min_width = $field["options"]["min_width"] ? intval($field["options"]["min_width"]) : 0;
	$min_height = $field["options"]["min_height"] ? intval($field["options"]["min_height"]) : 0;
?>
<div class="<? if (empty($field["options"]["image"])) { ?>upload_field<? } else { ?>image_field<? } ?>">
	<input<? if ($field["required"]) { ?> class="required"<? } ?> type="file" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" <? if (!empty($field["options"]["image"])) { ?> accept="image/*" <? } ?>/>
	<?	
		if (!isset($field["options"]["image"]) || !$field["options"]["image"]) {
			if ($field["value"]) {
				$pathinfo = BigTree::pathInfo($field["value"]);
	?>
	<div class="currently_file">
		<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
		<strong>Currently:</strong> <?=$pathinfo["basename"]?> <a href="#" class="remove_resource">Remove</a>
	</div>
	<?
			}
		} else {
			if ($field["value"]) {
				if ($field["options"]["preview_prefix"]) {
					$preview_image = BigTree::prefixFile($field["value"],$field["options"]["preview_prefix"]);
				} else {
					$preview_image = $field["value"];
				}
			} else {
				$preview_image = false;
			}
			
			// Generate the file manager restrictions
			$button_options = htmlspecialchars(json_encode(array(
				"minWidth" => $min_width,
				"minHeight" => $min_height,
				"currentlyKey" => $field["key"]
			)));
			
			if (!defined("BIGTREE_FRONT_END_EDITOR") && !$bigtree["form"]["embedded"]) {
	?>
	<span class="or">OR</span>
	<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button form_image_browser"><span class="icon_images"></span>Browse</a>
	<?
			}
	?>
	<br class="clear" />
	<div class="currently" id="<?=$field["id"]?>"<? if (!$field["value"]) { ?> style="display: none;"<? } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<? if ($preview_image) { ?>
			<img src="<?=$preview_image?>" alt="" />
			<? } ?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
	</div>
	<?
		}
	?>
</div>