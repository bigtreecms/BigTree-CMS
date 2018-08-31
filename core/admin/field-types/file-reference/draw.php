<?php
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(["currentlyKey" => $field["key"], "type" => "file"]));
	$name = $link = "";
	
	if ($field["value"]) {
		$resource = $admin->getResource($field["value"]);
		$name = $resource["name"];
	}
?>
<div class="upload_field<?php if ($field["options"]["validation"] == "required") { ?> reference_required<?php } ?>">
	<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_folder"></span>Browse</a>
	<br class="clear" />
	<div class="currently_file" id="<?=$field["id"]?>"<?php if (empty($field["value"])) { ?> style="display: none;"<?php } ?>>
		<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" class="js-input">
		<strong>Currently:</strong>
		<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$field["value"]?>/" target="_blank" class="js-current-file"><?=$name?></a>
		<a href="#" class="remove_resource">Remove</a>
	</div>
</div>