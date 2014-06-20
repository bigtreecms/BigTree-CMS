<div class="<? if (!isset($field["options"]["image"]) || !$field["options"]["image"]) { ?>upload_field<? } else { ?>image_field<? } ?>">
	<input<? if ($field["required"]) { ?> class="required"<? } ?> type="file" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" />
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
				"minWidth" => $field["options"]["min_width"],
				"minHeight" => $field["options"]["min_height"],
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