<div class="upload_field" id="<?=$field["id"]?>">
	<div class="contain">
		<input<?php if ($field["required"]) { ?> class="required"<?php } ?> type="file" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>"<?php if (!empty($field["settings"]["valid_extensions"])) { ?> accept="<?=BigTree::safeEncode($field["settings"]["valid_extensions"])?>"<?php } ?> />
		<?php
			if ($field["value"]) {
				$pathinfo = BigTree::pathInfo($field["value"]);
		?>
		<div class="currently_file">
			<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
			<strong>Currently:</strong> <a href="<?=$field["value"]?>" target="_blank"><?=$pathinfo["basename"]?></a> <a href="#" class="remove_resource">Remove</a>
		</div>
		<?php
			}
		?>
	</div>
</div>