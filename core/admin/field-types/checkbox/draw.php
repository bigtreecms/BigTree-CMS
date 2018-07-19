<?php
	if (!$field["has_value"] && !empty($field["settings"]["default_checked"])) {
		$field["value"] = "on";
	}
?>
<input<?php if ($field["required"]) { ?> class="required"<?php } ?> type="checkbox" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" id="<?=$field["id"]?>" <?php if ($field["value"]) { ?>checked="checked" <?php } ?><?php if ($field["settings"]["custom_value"]) { ?> value="<?=BigTree::safeEncode($field["settings"]["custom_value"])?>"<?php } ?> />
<?php if ($field["title"]) { ?>
<label<?php if ($field["required"]) { ?> class="required"<?php } ?> class="for_checkbox" for="<?=$field["id"]?>">
	<?=$field["title"]?><?php if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><?php } ?>
</label>
<?php } ?>