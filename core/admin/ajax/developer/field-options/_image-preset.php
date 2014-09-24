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
			<li class="thumbnail"><a href="#<?=$crop_count?>" title="Create Thumbnail of Crop" class="disabled"></a></li>
			<li class="colormode">
				<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
			</li>
			<li class="del"><a href="#<?=$crop_count?>" title="Remove" class="disabled"></a></li>
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
			<li class="up"><span class="icon_small icon_small_up"></span></li>
			<li class="colormode">
				<input type="hidden" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled<? if ($thumb["grayscale"]) { ?> gray<? } ?>"></a>
			</li>
			<li class="del"><a href="#" title="Remove" class="disabled"></a></li>
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
			<li class="colormode">
				<input type="hidden" name="thumbs[<?=$thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode" class="disabled<? if ($thumb["grayscale"]) { ?> gray<? } ?>"></a>
			</li>
			<li class="del"><a href="#" title="Remove" class="disabled"></a></li>
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
		ImageOptions.updateCounts(<?=$crop_count?>,<?=$crop_thumb_count?>,<?=$thumb_count?>);
	} catch (err) {}
</script>