<?php
	if (array_filter((array) $field["value"])) {
		if ($field["options"]["preview_prefix"]) {
			$preview_image = BigTree::prefixFile($field["value"]["image"], $field["options"]["preview_prefix"]);
		} else {
			$preview_image = $field["value"]["image"];
		}
	} else {
		$preview_image = false;
	}
?>
<div class="image_field">
	<input<?php if ($field["required"]) { ?> class="required"<?php } ?> type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[new]" id="<?=$field["id"]?>" placeholder="YouTube or Vimeo URL" />
	<?php if ($preview_image) { ?>
	<div class="currently">
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<img src="<?=$preview_image?>" alt="" />
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$field["key"]?>[existing]" value="<?=BigTree::safeEncode(json_encode($field["value"]))?>" />
	</div>
	<?php } ?>
</div>