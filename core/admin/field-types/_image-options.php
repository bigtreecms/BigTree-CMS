
<?php
	// Prevent warnings
	$settings = is_array($settings) ? $settings : array();

	$using_preset = false;
	$media_settings = BigTreeJSONDB::get("config", "media-settings");
	$presets = $media_settings["presets"];

	// See if we're using a preset and ensure it still exists
	if (!empty($settings["preset"])) {
		if ($presets[$settings["preset"]]) {
			$using_preset = true;
		} else {
			$settings = array();
		}
	}

	$settings["min_width"] = isset($settings["min_width"]) ? $settings["min_width"] : "";
	$settings["min_height"] = isset($settings["min_height"]) ? $settings["min_height"] : "";
	$settings["preview_prefix"] = isset($settings["preview_prefix"]) ? $settings["preview_prefix"] : "";
	$settings["crops"] = isset($settings["crops"]) ? $settings["crops"] : array();
	$settings["thumbs"] = isset($settings["thumbs"]) ? $settings["thumbs"] : array();

	// We use this file for creating presets so we don't want to show the dropdown in that context
	if (!defined("BIGTREE_CREATING_PRESET") && array_filter((array) $presets)) {
?>
<fieldset>
	<label for="<?=$image_options_prefix?>preset_select">Existing Preset</label>
	<select name="preset" id="<?=$image_options_prefix?>preset_select">
		<option></option>
		<?php
			foreach ($presets as $preset) {
		?>
		<option value="<?=$preset["id"]?>"<?php if (!empty($settings["preset"]) && $preset["id"] == $settings["preset"]) { ?> selected="selected"<?php } ?>><?=$preset["name"]?></option>
		<?php
			}
		?>
	</select>
</fieldset>
<?php
	}
?>
<div id="<?=$image_options_prefix?>image_options_container">
	<?php
		if ($using_preset) {
			include "_image-preset.php";
		} else {
	?>
	<fieldset>
		<label for="<?=$image_options_prefix?>settings_field_min_width">Minimum Width <small>(numeric value in pixels)</small></label>
		<input id="<?=$image_options_prefix?>settings_field_min_width" type="text" name="min_width" value="<?=BigTree::safeEncode($settings["min_width"])?>" />
	</fieldset>
	<fieldset>
		<label for="<?=$image_options_prefix?>settings_field_min_height">Minimum Height <small>(numeric value in pixels)</small></label>
		<input id="<?=$image_options_prefix?>settings_field_min_height" type="text" name="min_height" value="<?=BigTree::safeEncode($settings["min_height"])?>" />
	</fieldset>
	<fieldset>
		<label for="<?=$image_options_prefix?>settings_field_preview_prefix">Preview Prefix <small>(for forms)</small></label>
		<input id="<?=$image_options_prefix?>settings_field_preview_prefix" type="text" name="preview_prefix" value="<?=BigTree::safeEncode($settings["preview_prefix"])?>" />
	</fieldset>
	<fieldset>
		<label>Create Hi-Resolution Retina Images <small><a href="https://www.bigtreecms.org/docs/dev-guide/field-types/retina-images/" target="_blank">(learn more)</a></small></label>
		<input id="<?=$image_options_prefix?>settings_field_retina" type="checkbox" name="retina" <?php if (!empty($settings["retina"])) { ?>checked="checked" <?php } ?>/>
		<label for="<?=$image_options_prefix?>settings_field_retina" class="for_checkbox"> When Available</label>
	</fieldset>

	<h4>Crops <a href="#" class="add_crop icon_small icon_small_add"></a></h4>
	<fieldset>
		<div class="image_attr" id="<?=$image_options_prefix?>pop_crop_list">
			<ul>
				<li>Prefix:</li><li>Width:</li><li>Height:</li>
			</ul>
			<?php
				$crop_count = 0;
				$crop_thumb_count = 0;
				$crop_sub_count = 0;

				if (is_array($settings["crops"])) {
					foreach ($settings["crops"] as $crop) {
						// In case a crop was added but no options were set
						if (is_array($crop) && $crop["width"] && $crop["height"]) {
							$crop_count++;
			?>
			<ul>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][prefix]" value="<?=BigTree::safeEncode($crop["prefix"] ?? "")?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][width]" value="<?=BigTree::safeEncode($crop["width"] ?? "")?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][height]" value="<?=BigTree::safeEncode($crop["height"] ?? "")?>" />
				</li>
				<li class="actions">
					<a href="#<?=$crop_count?>" title="Create Centered Sub-Crop" class="subcrop"></a>
					<a href="#<?=$crop_count?>" title="Create Thumbnail of Crop" class="thumbnail"></a>
					<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"] ?? ""?>" />
					<a href="#" title="Switch Color Mode" class="color_mode<?php if (!empty($crop["grayscale"])) { ?> gray<?php } ?>"></a>
					<a href="#<?=$crop_count?>" title="Remove" class="delete"></a>
				</li>
			</ul>
			<div class="image_attr_crop_thumbs">
				<?php
								if (!empty($crop["thumbs"]) && is_array($crop["thumbs"])) {
									foreach ($crop["thumbs"] as $thumb) {
										// In case a thumb was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
										if (is_array($thumb) && $thumb["prefix"] && (!empty($thumb["width"]) || !empty($thumb["height"]))) {
											$crop_thumb_count++;
				?>
				<ul class="image_attr_thumbs_<?=$crop_count?>">
					<li class="thumbed">
						<span class="icon_small icon_small_picture" title="Thumbnail"></span>
						<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][prefix]" value="<?=BigTree::safeEncode($thumb["prefix"] ?? "")?>" />
					</li>
					<li>
						<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][width]" value="<?=BigTree::safeEncode($thumb["width"] ?? "")?>" />
					</li>
					<li>
						<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][height]" value="<?=BigTree::safeEncode($thumb["height"] ?? "")?>" />
					</li>
					<li class="actions">
						<span class="icon_small icon_small_up"></span>
						<input type="hidden" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][grayscale]" value="<?=$thumb["grayscale"] ?? ""?>" />
						<a href="#" title="Switch Color Mode" class="color_mode<?php if (!empty($thumb["grayscale"])) { ?> gray<?php } ?>"></a>
						<a href="#" title="Remove" class="delete"></a>
					</li>
				</ul>
				<?php
									}
								}
							}
				?>
			</div>
			<div class="image_attr_crop_center_crops">
				<?php
							if (!empty($crop["center_crops"]) && is_array($crop["center_crops"])) {
								foreach ($crop["center_crops"] as $center_crop) {
									// In case a sub crop was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
									if (is_array($center_crop) && $center_crop["prefix"] && $center_crop["width"] && $center_crop["height"]) {
										$crop_sub_count++;
				?>
				<ul class="image_attr_thumbs_<?=$crop_count?>">
					<li class="thumbed">
						<span class="icon_small icon_small_crop" title="Sub-Crop"></span>
						<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][prefix]" value="<?=BigTree::safeEncode($center_crop["prefix"] ?? "")?>" />
					</li>
					<li>
						<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][width]" value="<?=BigTree::safeEncode($center_crop["width"] ?? "")?>" />
					</li>
					<li>
						<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][height]" value="<?=BigTree::safeEncode($center_crop["height"] ?? "")?>" />
					</li>
					<li class="actions">
						<span class="icon_small icon_small_up"></span>
						<input type="hidden" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][grayscale]" value="<?=$center_crop["grayscale"] ?? ""?>" />
						<a href="#" title="Switch Color Mode" class="color_mode<?php if (!empty($center_crop["grayscale"])) { ?> gray<?php } ?>"></a>
						<a href="#" title="Remove" class="delete"></a>
					</li>
				</ul>
				<?php
									}
								}
							}
				?>
			</div>
			<?php
						}
					}
				}
			?>
		</div>
	</fieldset>

	<h4>Thumbnails <a href="#" class="add_thumb icon_small icon_small_add"></a></h4>
	<p class="error_message" style="display: none;" id="<?=$image_options_prefix?>thumbnail_dialog_error">You must enter a height or width for each thumbnail.</p>
	<fieldset>
		<div class="image_attr" id="<?=$image_options_prefix?>pop_thumb_list">
			<ul>
				<li>Prefix:</li><li>Width:</li><li>Height:</li>
			</ul>
			<?php
				// Keep a count of thumbs
				$thumb_count = 0;

				if (!empty($settings["thumbs"]) && is_array($settings["thumbs"])) {
					foreach ($settings["thumbs"] as $thumb) {
						// Make sure a width or height was entered or it's pointless
						if (is_array($thumb) && ($thumb["width"] || $thumb["height"])) {
							$thumb_count++;
			?>
			<ul>
				<li>
					<input type="text" name="thumbs[<?=$thumb_count?>][prefix]" value="<?=BigTree::safeEncode($thumb["prefix"] ?? "")?>" />
				</li>
				<li>
					<input type="text" name="thumbs[<?=$thumb_count?>][width]" value="<?=BigTree::safeEncode($thumb["width"] ?? "")?>" />
				</li>
				<li>
					<input type="text" name="thumbs[<?=$thumb_count?>][height]" value="<?=BigTree::safeEncode($thumb["height"] ?? "")?>" />
				</li>
				<li class="actions for_thumbnail">
					<input type="hidden" name="thumbs[<?=$thumb_count?>][grayscale]" value="<?=$thumb["grayscale"] ?? ""?>" />
					<a href="#" title="Switch Color Mode" class="color_mode<?php if (!empty($thumb["grayscale"])) { ?> gray<?php } ?>"></a>
					<a href="#" title="Remove" class="delete"></a>
				</li>
			</ul>
			<?php
						}
					}
				}
			?>
		</div>
	</fieldset>

	<h4>Center Crops <small>(automatically crops from the center of image)</small> <a href="#" class="add_center_crop icon_small icon_small_add"></a></h4>
	<fieldset>
		<div class="image_attr" id="<?=$image_options_prefix?>pop_center_crop_list">
			<ul>
				<li>Prefix:</li><li>Width:</li><li>Height:</li>
			</ul>
			<?php
				// Keep a count of center crops
				$center_crop_count = 0;

				if (!empty($settings["center_crops"]) && is_array($settings["center_crops"])) {
					foreach ($settings["center_crops"] as $center_crop) {
						// Make sure a width and height was entered or it's pointless
						if (is_array($center_crop) && ($center_crop["width"] && $center_crop["height"])) {
							$center_crop_count++;
			?>
			<ul>
				<li>
					<input type="text" name="center_crops[<?=$center_crop_count?>][prefix]" value="<?=BigTree::safeEncode($center_crop["prefix"] ?? "")?>" />
				</li>
				<li>
					<input type="text" name="center_crops[<?=$center_crop_count?>][width]" value="<?=BigTree::safeEncode($center_crop["width"] ?? "")?>" />
				</li>
				<li>
					<input type="text" name="center_crops[<?=$center_crop_count?>][height]" value="<?=BigTree::safeEncode($center_crop["height"] ?? "")?>" />
				</li>
				<li class="actions for_thumbnail">
					<input type="hidden" name="center_crops[<?=$center_crop_count?>][grayscale]" value="<?=$center_crop["grayscale"] ?? ""?>" />
					<a href="#" title="Switch Color Mode" class="color_mode<?php if (!empty($center_crop["grayscale"])) { ?> gray<?php } ?>"></a>
					<a href="#<?=$center_crop_count?>" title="Remove" class="delete"></a>
				</li>
			</ul>
			<?php
						}
					}
				}
			?>
		</div>
	</fieldset>
	<?php
		}
	?>
</div>

<script>
	var <?=$image_options_prefix?>ImageOptions = (function() {
		var CenterCropCount = <?=$center_crop_count?>;
		var CropCount = <?=$crop_count?>;
		var CropSubCount = <?=$crop_sub_count?>;
		var CropThumbCount = <?=$crop_thumb_count?>;
		var OptionsContainer = $("#<?=$image_options_prefix?>image_options_container");
		var PopCropList = $("#<?=$image_options_prefix?>pop_crop_list");
		var PopCenterCropList = $("#<?=$image_options_prefix?>pop_center_crop_list");
		var PopThumbList = $("#<?=$image_options_prefix?>pop_thumb_list");
		var PresetSelect = $("#<?=$image_options_prefix?>preset_select");
		var ThumbCount = <?=$thumb_count?>;

		function addCenterCrop(ev) {
			ev.preventDefault();

			CenterCropCount++;
			PopCenterCropList.append('<ul class="require_width_or_height">' +
				'<li><input type="text" name="center_crops[' + CenterCropCount + '][prefix]" value="" /></li>' +
				'<li><input type="text" name="center_crops[' + CenterCropCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="center_crops[' + CenterCropCount + '][height]" value="" /></li>' +
				'<li class="actions for_thumbnail">' +
					'<input type="hidden" name="center_crops[' + CenterCropCount + '][grayscale]" value="" />' +
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#' + CenterCropCount + '" title="Remove" class="delete"></a>' +
				'</li></ul>');
		}

		function addCrop(ev) {
			ev.preventDefault();

			CropCount++;
			PopCropList.append('<ul>' +
				'<li><input type="text" name="crops[' + CropCount + '][prefix]" value="" /></li>' +
				'<li><input type="text" class="required" name="crops[' + CropCount + '][width]" value="" /></li>' +
				'<li><input type="text" class="required" name="crops[' + CropCount + '][height]" value="" /></li>' +
				'<li class="actions">' +
					'<a href="#' + CropCount + '" title="Create Centered Sub-Crop" class="subcrop"></a>' +
					'<a href="#' + CropCount + '" title="Create Thumbnail of Crop" class="thumbnail"></a>' +
					'<input type="hidden" name="crops[' + CropCount + '][grayscale]" value="" />' +
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#' + CropCount + '" title="Remove" class="delete"></a>' +
				'</li></ul>' +
				'<div class="image_attr_crop_thumbs"></div>' +
				'<div class="image_attr_crop_center_crops"></div>');
		}

		function addThumb(ev) {
			ev.preventDefault();

			ThumbCount++;
			PopThumbList.append('<ul class="require_width_or_height">' +
				'<li><input type="text" name="thumbs[' + ThumbCount + '][prefix]" value="" /></li>' +
				'<li><input type="text" name="thumbs[' + ThumbCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="thumbs[' + ThumbCount + '][height]" value="" /></li>' +
				'<li class="actions for_thumbnail">' +
					'<input type="hidden" name="thumbs[' + ThumbCount + '][grayscale]" value="" />' +
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#" title="Remove" class="delete"></a>' +
				'</li></ul>');
		}

		function updateCounts(crop,crop_thumb,thumb,center_crop,crop_sub) {
			CropCount = crop;
			CropThumbCount = crop_thumb;
			ThumbCount = thumb;
			CenterCropCount = center_crop;
			CropSubCount = crop_sub;
		}

		// Prevent clicks to disabled things
		OptionsContainer.on("click",".disabled",function(ev) {
			ev.preventDefault();
			ev.stopImmediatePropagation();

		// Remove a thumbnail or crop
		}).on("click",".image_attr .delete",function(ev) {
			ev.preventDefault();

			if ($(this).attr("href") != "#") {
				var count = $(this).attr("href").substr(1);
				OptionsContainer.find(".image_attr_thumbs_" + count).remove();
			}

			$(this).parents("ul").remove();

		// Add thumbnail of crops
		}).on("click",".image_attr .thumbnail",function(ev) {
			ev.preventDefault();

			var count = $(this).attr("href").substr(1);
			CropThumbCount++;
			$(this).parents("ul").next(".image_attr_crop_thumbs").append('<ul class="require_width_or_height image_attr_thumbs_' + count + '">' +
				'<li class="thumbed">' +
					'<span class="icon_small icon_small_picture" title="Thumbnail"></span>' +
					'<input type="text" class="image_attr_thumbs" name="crops[' + count + '][thumbs][' + CropThumbCount + '][prefix]" value="" />' +
				'</li>' +
				'<li><input type="text" name="crops[' + count + '][thumbs][' + CropThumbCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="crops[' + count + '][thumbs][' + CropThumbCount + '][height]" value="" /></li>' +
				'<li class="actions">' +
					'<span class="icon_small icon_small_up"></span>' +
					'<input type="hidden" name="crops[' + count + '][thumbs][' + CropThumbCount + '][grayscale]" value="" />' +
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#" title="Remove" class="delete"></a>' +
				'</li></ul>');

		// Add sub-crop of crops
		}).on("click",".image_attr .subcrop",function(ev) {
			ev.preventDefault();

			var count = $(this).attr("href").substr(1);
			CropSubCount++;
			$(this).parents("ul").nextAll(".image_attr_crop_center_crops:first").append('<ul class="require_width_or_height image_attr_thumbs_' + count + '">' +
				'<li class="thumbed">' +
					'<span class="icon_small icon_small_crop" title="Sub-Crop"></span>' +
					'<input type="text" class="image_attr_thumbs" name="crops[' + count + '][center_crops][' + CropSubCount + '][prefix]" value="" />' +
				'</li>' +
				'<li><input type="text" name="crops[' + count + '][center_crops][' + CropSubCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="crops[' + count + '][center_crops][' + CropSubCount + '][height]" value="" /></li>' +
				'<li class="actions">' +
					'<span class="icon_small icon_small_up"></span>' +
					'<input type="hidden" name="crops[' + count + '][center_crops][' + CropSubCount + '][grayscale]" value="" />' +
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#" title="Remove" class="delete"></a>' +
				'</li></ul>');

		// Switch between color and grayscale
		}).on("click",".image_attr .color_mode",function(ev) {
			ev.preventDefault();

			$(this).toggleClass("gray");
			if ($(this).hasClass("gray")) {
				$(this).prev("input").val("on");
			} else {
				$(this).prev("input").val("");
			}

		// Allow you to hit enter in a crop/thumb box to create another automatically
		}).on("keydown","#<?=$image_options_prefix?>pop_crop_list input",function(ev) {
			if (ev.keyCode == 13) {
				ev.preventDefault();
				addCrop();
			}

		}).on("keydown","#<?=$image_options_prefix?>pop_thumb_list input",function(ev) {
			if (ev.keyCode == 13) {
				ev.preventDefault();
				addThumb();
			}

		// Hook Add Buttons
		}).on("click",".add_crop",addCrop).on("click",".add_thumb",addThumb).on("click",".add_center_crop",addCenterCrop);

		// Preset choosing
		PresetSelect.change(function() {
			if ($(this).val()) {
				OptionsContainer.load("<?=ADMIN_ROOT?>ajax/developer/media/load-preset/", { id: $(this).val(), prefix: "<?=$image_options_prefix?>" }, function() {
					BigTreeCustomControls();
				});
			} else {
				OptionsContainer.find("input").prop("disabled",false);
				OptionsContainer.find(".disabled").removeClass("disabled");
				OptionsContainer.find(".add_crop, .add_thumb").show();
			}
		});

		// Make sure a height and width is entered for each crop
		$(".bigtree_dialog_form").last().submit(function(ev) {
			var errors = false;

			// Find required fields for crops
			$(this).find("input.required").each(function(index,el) {
				if (!$(el).val()) {
					errors = true;
					$(el).addClass("error");
				}
			});

			if (errors) {
				ev.stopImmediatePropagation();
				return false;
			}
		});

		return { updateCounts: updateCounts };
	})();
</script>