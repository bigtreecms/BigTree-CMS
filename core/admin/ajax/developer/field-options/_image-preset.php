<?
	$settings = BigTreeCMS::getSetting("bigtree-internal-media-settings");
	$data = isset($data["preset"]) ? $settings["presets"][$data["preset"]] : $settings["presets"][$_POST["id"]];
?>
<fieldset>
	<label>Minimum Width <small>(numeric value in pixels)</small></label>
	<input type="text" name="min_width" value="<?=htmlspecialchars($data["min_width"])?>" disabled="disabled" />
</fieldset>
<fieldset>
	<label>Minimum Height <small>(numeric value in pixels)</small></label>
	<input type="text" name="min_height" value="<?=htmlspecialchars($data["min_height"])?>" disabled="disabled" />
</fieldset>
<fieldset>
	<label>Preview Prefix <small>(for forms)</small></label>
	<input type="text" name="preview_prefix" value="<?=htmlspecialchars($data["preview_prefix"])?>" disabled="disabled" />
</fieldset>
<fieldset>
	<label>Create Hi-Resolution Retina Images <small><a href="http://www.bigtreecms.org/docs/dev-guide/field-types/retina-images/" target="_blank">(learn more)</a></small></label>
	<input type="checkbox" name="retina" <? if ($data["retina"]) { ?>checked="checked" <? } ?> disabled="disabled" />
	<label class="for_checkbox"> When Available</label>
</fieldset>

<h4>Crops <a href="#" class="add_crop icon_small icon_small_add" style="display: none;"></a></h4>
<fieldset>
	<div class="image_attr" id="pop_crop_list">
		<ul>
			<li>Prefix:</li><li>Width:</li><li>Height:</li>
		</ul>
		<?
			$crop_count = 0;
			$crop_thumb_count = 0;
			$crop_sub_count = 0;
			if (is_array($data["crops"])) {
				foreach ($data["crops"] as $crop) {
					// In case a crop was added but no options were set
					if (is_array($crop) && $crop["width"] && $crop["height"]) {
						$crop_count++;
		?>
		<ul>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<a href="#<?=$crop_count?>" title="Create Centered Sub-Crop" class="subcrop disabled"></a>
				<a href="#<?=$crop_count?>" title="Create Thumbnail of Crop" class="thumbnail disabled"></a>
				<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
				<a href="#<?=$crop_count?>" title="Remove" class="disabled delete"></a>
			</li>
		</ul>
		<?
						if (is_array($crop["thumbs"])) {
							foreach ($crop["thumbs"] as $thumb) {
								// In case a thumb was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
								if (is_array($thumb) && $thumb["prefix"] && ($thumb["width"] || $thumb["height"])) {
									$crop_thumb_count++;
		?>
		<ul class="image_attr_thumbs_<?=$crop_count?>">
			<li class="thumbed">
				<span class="icon_small icon_small_picture" title="Thumbnail"></span>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<span class="icon_small icon_small_up disabled"></span>
				<input type="hidden" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled color_mode<? if ($thumb["grayscale"]) { ?> gray<? } ?>"></a>
				<a href="#" title="Remove" class="disabled delete"></a>
			</li>
		</ul>
		<?
								}
							}
						}

						if (is_array($crop["center_crops"])) {
							foreach ($crop["center_crops"] as $crop) {
								// In case a sub crop was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
								if (is_array($crop) && $crop["prefix"] && $crop["width"] && $crop["height"]) {
									$crop_sub_count++;
		?>
		<ul class="image_attr_thumbs_<?=$crop_count?>">
			<li class="thumbed">
				<span class="icon_small icon_small_crop" title="Sub-Crop"></span>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<span class="disabled icon_small icon_small_up"></span>
				<input type="hidden" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
				<a href="#" title="Remove" class="disabled delete"></a>
			</li>
		</ul>
		<?
								}
							}
						}
					}
				}
			}
		?>
	</div>
</fieldset>

<h4>Thumbnails <a href="#" class="add_thumb icon_small icon_small_add" style="display: none;"></a></h4>
<p class="error_message" style="display: none;" id="thumbnail_dialog_error">You must enter a height or width for each thumbnail.</p>
<fieldset>
	<div class="image_attr" id="pop_thumb_list">
		<ul>
			<li>Prefix:</li><li>Width:</li><li>Height:</li>
		</ul>
		<?
			// Keep a count of thumbs
			$thumb_count = 0;
			if (is_array($data["thumbs"])) {
				foreach ($data["thumbs"] as $thumb) {
					// Make sure a width or height was entered or it's pointless
					if (is_array($thumb) && ($thumb["width"] || $thumb["height"])) {
						$thumb_count++;
		?>
		<ul>
			<li>
				<input type="text" name="thumbs[<?=$thumb_count?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="thumbs[<?=$thumb_count?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="thumbs[<?=$thumb_count?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" disabled="disabled" />
			</li>
			<li class="actions for_thumbnail">
				<input type="hidden" name="thumbs[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
				<a href="#<?=$crop_count?>" title="Remove" class="disabled delete"></a>
			</li>
		</ul>
		<?
					}
				}
			}
		?>
	</div>
</fieldset>

<h4>Center Crops <small>(automatically crops from the center of image)</small> <a href="#" class="add_center_crop icon_small icon_small_add" style="display: none;"></a></h4>
<fieldset>
	<div class="image_attr" id="pop_center_crop_list">
		<ul>
			<li>Prefix:</li><li>Width:</li><li>Height:</li>
		</ul>
		<?
			// Keep a count of center crops
			$center_crop_count = 0;
			if (is_array($data["center_crops"])) {
				foreach ($data["center_crops"] as $crop) {
					// Make sure a width and height was entered or it's pointless
					if (is_array($crop) && ($crop["width"] && $crop["height"])) {
						$center_crop_count++;
		?>
		<ul>
			<li>
				<input type="text" name="center_crops[<?=$center_crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="center_crops[<?=$center_crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="center_crops[<?=$center_crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions for_thumbnail">
				<input type="hidden" name="center_crops[<?=$center_crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
				<a href="#<?=$center_crop_count?>" title="Remove" class="disabled delete"></a>
			</li>
		</ul>
		<?
					}
				}
			}
		?>
	</div>
</fieldset>
<script>
	try {
		ImageOptions.updateCounts(<?=$crop_count?>,<?=$crop_thumb_count?>,<?=$thumb_count?>,<?=$center_crop_count?>,<?=$crop_sub_count?>);
	} catch (err) {}
</script>