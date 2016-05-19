<?php
	namespace BigTree;

	$settings = \BigTreeCMS::getSetting("bigtree-internal-media-settings");
	$data = isset($data["preset"]) ? $settings["presets"][$data["preset"]] : $settings["presets"][$_POST["id"]];

	// Translate title text that gets repeated
	$subcrop_title = Text::translate("Sub-Crop", true);
	$center_subcrop_title = Text::translate("Create Centered Sub-Crop", true);
	$crop_thumb_title = Text::translate("Create Thumbnail of Crop", true);
	$color_mode_title = Text::translate("Switch Color Mode", true);
	$remove_title = Text::translate("Remove", true);
	$thumb_title = Text::translate("Thumbnail", true);
	$prefix_title = Text::translate("Prefix");
	$width_title = Text::translate("Width");
	$height_title = Text::translate("Height");
?>
<fieldset>
	<label><?=Text::translate("Minimum Width <small>(numeric value in pixels)</small>")?></label>
	<input type="text" name="min_width" value="<?=htmlspecialchars($data["min_width"])?>" disabled="disabled" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Minimum Height <small>(numeric value in pixels)</small>")?></label>
	<input type="text" name="min_height" value="<?=htmlspecialchars($data["min_height"])?>" disabled="disabled" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Preview Prefix <small>(for forms)</small>")?></label>
	<input type="text" name="preview_prefix" value="<?=htmlspecialchars($data["preview_prefix"])?>" disabled="disabled" />
</fieldset>
<fieldset>
	<label><?=Text::translate('Create Hi-Resolution Retina Images <small><a href=":doc_link:" target="_blank">(learn more)</a></small>', false, array(":doc_link:" => "http://www.bigtreecms.org/docs/dev-guide/field-types/retina-images/"))?></label>
	<input type="checkbox" name="retina" <?php if ($data["retina"]) { ?>checked="checked" <?php } ?> disabled="disabled" />
	<label class="for_checkbox"> <?=Text::translate("When Available")?></label>
</fieldset>

<h4><?=Text::translate("Crops")?> <a href="#" class="add_crop icon_small icon_small_add" style="display: none;"></a></h4>
<fieldset>
	<div class="image_attr" id="pop_crop_list">
		<ul>
			<li><?=$prefix_title?></li>
			<li><?=$width_title?></li>
			<li><?=$height_title?></li>
		</ul>
		<?php
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
				<a href="#<?=$crop_count?>" title="<?=$center_subcrop_title?>" class="subcrop disabled"></a>
				<a href="#<?=$crop_count?>" title="<?=$crop_thumb_title?>" class="thumbnail disabled"></a>
				<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="<?=$color_mode_title?>" class="disabled color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#<?=$crop_count?>" title="<?=$remove_title?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
						if (is_array($crop["thumbs"])) {
							foreach ($crop["thumbs"] as $thumb) {
								// In case a thumb was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
								if (is_array($thumb) && $thumb["prefix"] && ($thumb["width"] || $thumb["height"])) {
									$crop_thumb_count++;
		?>
		<ul class="image_attr_thumbs_<?=$crop_count?>">
			<li class="thumbed">
				<span class="icon_small icon_small_picture" title="<?=$thumb_title?>"></span>
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
				<a href="#" title="<?=$color_mode_title?>" class="disabled color_mode<?php if ($thumb["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#" title="<?=$remove_title?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
								}
							}
						}

						if (is_array($crop["center_crops"])) {
							foreach ($crop["center_crops"] as $subcrop) {
								// In case a sub crop was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
								if (is_array($subcrop) && $subcrop["prefix"] && $subcrop["width"] && $subcrop["height"]) {
									$crop_sub_count++;
		?>
		<ul class="image_attr_thumbs_<?=$crop_count?>">
			<li class="thumbed">
				<span class="icon_small icon_small_crop" title="<?=$subcrop_title?>"></span>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][prefix]" value="<?=htmlspecialchars($subcrop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][width]" value="<?=htmlspecialchars($subcrop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][height]" value="<?=htmlspecialchars($subcrop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<span class="disabled icon_small icon_small_up"></span>
				<input type="hidden" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][grayscale]" value="<?=$subcrop["grayscale"]?>" />
				<a href="#" title="<?=$color_mode_title?>" class="disabled color_mode<?php if ($subcrop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#" title="<?=$remove_title?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
								}
							}
						}
					}
				}
			}
		?>
	</div>
</fieldset>

<h4><?=Text::translate("Thumbnails")?> <a href="#" class="add_thumb icon_small icon_small_add" style="display: none;"></a></h4>
<p class="error_message" style="display: none;" id="thumbnail_dialog_error"><?=Text::translate("You must enter a height or width for each thumbnail.")?></p>
<fieldset>
	<div class="image_attr" id="pop_thumb_list">
		<ul>
			<li><?=$prefix_title?></li>
			<li><?=$width_title?></li>
			<li><?=$height_title?></li>
		</ul>
		<?php
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
				<a href="#" title="<?=$color_mode_title?>" class="disabled color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#<?=$crop_count?>" title="<?=$remove_title?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
					}
				}
			}
		?>
	</div>
</fieldset>

<h4><?=Text::translate("Center Crops <small>(automatically crops from the center of image)</small>")?> <a href="#" class="add_center_crop icon_small icon_small_add" style="display: none;"></a></h4>
<fieldset>
	<div class="image_attr" id="pop_center_crop_list">
		<ul>
			<li><?=$prefix_title?></li>
			<li><?=$width_title?></li>
			<li><?=$height_title?></li>
		</ul>
		<?php
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
				<a href="#" title="<?=$color_mode_title?>" class="disabled color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#<?=$center_crop_count?>" title="<?=$remove_title?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
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