<?php
	if ($field["value"]) {
		$resource = $admin->getResource($field["value"]);
		
		if ($resource) {
			$preview_image = BigTree::prefixFile($resource["file"], "list-preview/");
		}
	}
	
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode([
		"currentlyKey" => $field["key"],
		"type" => "image",
		"minWidth" => intval($field["settings"]["min_width"]),
		"minHeight" => intval($field["settings"]["min_height"])
	]));
?>
<div class="image_field<?php if ($field["settings"]["validation"] == "required") { ?> reference_required<?php } ?>">
	<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_images"></span>Browse</a>
	<br class="clear" />
	<div class="currently currently_image_reference" id="<?=$field["id"]?>"<?php if (!$field["value"] || !$resource) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php if ($preview_image) { ?>
			<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$field["value"]?>/" target="_blank"><img src="<?=$preview_image?>" alt="" /></a>
			<?php } ?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
	</div>
</div>