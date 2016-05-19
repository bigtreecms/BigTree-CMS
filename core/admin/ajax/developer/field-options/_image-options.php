<?php
	namespace BigTree;

	// Prevent warnings
	$data = is_array($data) ? $data : array();

	$using_preset = false;
	$settings = \BigTreeCMS::getSetting("bigtree-internal-media-settings");
	// See if we're using a preset and ensure it still exists
	if ($data["preset"]) {
		if ($settings["presets"][$data["preset"]]) {
			$using_preset = true;
		} else {
			$data = array();
		}
	} 

	$data["min_width"] = isset($data["min_width"]) ? $data["min_width"] : "";
	$data["min_height"] = isset($data["min_height"]) ? $data["min_height"] : "";
	$data["preview_prefix"] = isset($data["preview_prefix"]) ? $data["preview_prefix"] : "";
	$data["crops"] = isset($data["crops"]) ? $data["crops"] : array();
	$data["thumbs"] = isset($data["thumbs"]) ? $data["thumbs"] : array();

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

	// We use this file for creating presets so we don't want to show the dropdown in that context
	if (!defined("BIGTREE_CREATING_PRESET") && array_filter((array)$settings["presets"])) {
?>
<fieldset>
	<label><?=Text::translate("Existing Preset")?></label>
	<select name="preset" id="preset_select">
		<option></option>
		<?php
			foreach ($settings["presets"] as $preset) {
		?>
		<option value="<?=$preset["id"]?>"<?php if ($preset["id"] == $data["preset"]) { ?> selected="selected"<?php } ?>><?=$preset["name"]?></option>
		<?php
			}
		?>
	</select>
</fieldset>
<?php
	}
?>
<div id="image_options_container">
	<?php
		if ($using_preset) {
			include "_image-preset.php";
		} else {
	?>
	<fieldset>
		<label><?=Text::translate("Minimum Width <small>(numeric value in pixels)</small>")?></label>
		<input type="text" name="min_width" value="<?=htmlspecialchars($data["min_width"])?>" />
	</fieldset>
	<fieldset>
		<label><?=Text::translate("Minimum Height <small>(numeric value in pixels)</small>")?></label>
		<input type="text" name="min_height" value="<?=htmlspecialchars($data["min_height"])?>" />
	</fieldset>
	<fieldset>
		<label><?=Text::translate("Preview Prefix <small>(for forms)</small>")?></label>
		<input type="text" name="preview_prefix" value="<?=htmlspecialchars($data["preview_prefix"])?>" />
	</fieldset>
	<fieldset>
		<label><?=Text::translate('Create Hi-Resolution Retina Images <small><a href=":doc_link:" target="_blank">(learn more)</a></small>', false, array(":doc_link:" => "http://www.bigtreecms.org/docs/dev-guide/field-types/retina-images/"))?></label>
		<input type="checkbox" name="retina" <?php if ($data["retina"]) { ?>checked="checked" <?php } ?>/>
		<label class="for_checkbox"> <?=Text::translate("When Available")?></label>
	</fieldset>
	
	<h4><?=Text::translate("Crops")?> <a href="#" class="add_crop icon_small icon_small_add"></a></h4>
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
					<input type="text" name="crops[<?=$crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" />
				</li>
				<li class="actions">
					<a href="#<?=$crop_count?>" title="<?=$center_subcrop_title?>" class="subcrop"></a>
					<a href="#<?=$crop_count?>" title="<?=$crop_thumb_title?>" class="thumbnail"></a>
					<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
					<a href="#" title="<?=$color_mode_title?>" class="color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
					<a href="#<?=$crop_count?>" title="<?=$remove_title?>" class="delete"></a>
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
					<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" />
				</li>
				<li class="actions">
					<span class="icon_small icon_small_up"></span>
					<input type="hidden" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
					<a href="#" title="<?=$color_mode_title?>" class="color_mode<?php if ($thumb["grayscale"]) { ?> gray<?php } ?>"></a>
					<a href="#" title="<?=$remove_title?>" class="delete"></a>
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
					<span class="icon_small icon_small_crop" title="<?=$sub_crop_title?>"></span>
					<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][prefix]" value="<?=htmlspecialchars($subcrop["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][width]" value="<?=htmlspecialchars($subcrop["width"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][height]" value="<?=htmlspecialchars($subcrop["height"])?>" />
				</li>
				<li class="actions">
					<span class="icon_small icon_small_up"></span>
					<input type="hidden" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][grayscale]" value="<?=$subcrop["grayscale"]?>" />
					<a href="#" title="<?=$color_mode_title?>" class="color_mode<?php if ($subcrop["grayscale"]) { ?> gray<?php } ?>"></a>
					<a href="#" title="<?=$remove_title?>" class="delete"></a>
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
	
	<h4><?=Text::translate("Thumbnails")?> <a href="#" class="add_thumb icon_small icon_small_add"></a></h4>
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
					<input type="text" name="thumbs[<?=$thumb_count?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="thumbs[<?=$thumb_count?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" />
				</li>
				<li>
					<input type="text" name="thumbs[<?=$thumb_count?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" />
				</li>
				<li class="actions for_thumbnail">
					<input type="hidden" name="thumbs[<?=$thumb_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
					<a href="#" title="<?=$color_mode_title?>" class="color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
					<a href="#<?=$crop_count?>" title="<?=$remove_title?>" class="delete"></a>
				</li>
			</ul>
			<?php
						}
					}
				}
			?>
		</div>
	</fieldset>
	
	<h4><?=Text::translate("Center Crops <small>(automatically crops from the center of image)</small>")?> <a href="#" class="add_center_crop icon_small icon_small_add"></a></h4>
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
					<input type="text" name="center_crops[<?=$center_crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="center_crops[<?=$center_crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" />
				</li>
				<li>
					<input type="text" name="center_crops[<?=$center_crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" />
				</li>
				<li class="actions for_thumbnail">
					<input type="hidden" name="center_crops[<?=$center_crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
					<a href="#" title="<?=$color_mode_title?>" class="color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
					<a href="#<?=$center_crop_count?>" title="<?=$remove_title?>" class="delete"></a>
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
	var ImageOptions = (function() {
		var CenterCropCount = <?=$center_crop_count?>;
		var CropCount = <?=$crop_count?>;
		var CropSubCount = <?=$crop_sub_count?>;
		var CropThumbCount = <?=$crop_thumb_count?>;
		var OptionsContainer = $("#image_options_container");
		var ThumbCount = <?=$thumb_count?>;

		function addCenterCrop(ev) {
			ev.preventDefault();

			CenterCropCount++;
			$("#pop_center_crop_list").append('<ul class="require_width_or_height">' +
				'<li><input type="text" name="center_crops[' + CenterCropCount + '][prefix]" value="" /></li>' +
				'<li><input type="text" name="center_crops[' + CenterCropCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="center_crops[' + CenterCropCount + '][height]" value="" /></li>' +
				'<li class="actions for_thumbnail">' +
					'<input type="hidden" name="center_crops[' + CenterCropCount + '][grayscale]" value="" />' +
					'<a href="#" title="<?=$color_mode_title?>" class="color_mode"></a>' +
					'<a href="#' + CenterCropCount + '" title="<?=$remove_title?>" class="delete"></a>' +
				'</li></ul>');
		}

		function addCrop(ev) {
			ev.preventDefault();

			CropCount++;
			$("#pop_crop_list").append('<ul>' +
				'<li><input type="text" name="crops[' + CropCount + '][prefix]" value="" /></li>' +
				'<li><input type="text" class="required" name="crops[' + CropCount + '][width]" value="" /></li>' +
				'<li><input type="text" class="required" name="crops[' + CropCount + '][height]" value="" /></li>' +
				'<li class="actions">' +
					'<a href="#' + CropCount + '" title="<?=$center_subcrop_title?>" class="subcrop"></a>' +
					'<a href="#' + CropCount + '" title="<?=$crop_thumb_title?>" class="thumbnail"></a>' +
					'<input type="hidden" name="crops[' + CropCount + '][grayscale]" value="" />' +
					'<a href="#" title="<?=$color_mode_title?>" class="color_mode"></a>' +
					'<a href="#' + CropCount + '" title="<?=$remove_title?>" class="delete"></a>' +
				'</li></ul>');
		}

		function addThumb(ev) {
			ev.preventDefault();

			ThumbCount++;
			$("#pop_thumb_list").append('<ul class="require_width_or_height">' +
				'<li><input type="text" name="thumbs[' + ThumbCount + '][prefix]" value="" /></li>' +
				'<li><input type="text" name="thumbs[' + ThumbCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="thumbs[' + ThumbCount + '][height]" value="" /></li>' +
				'<li class="actions for_thumbnail">' +
					'<input type="hidden" name="thumbs[' + ThumbCount + '][grayscale]" value="" />' +
					'<a href="#" title="<?=$color_mode_title?>" class="color_mode"></a>' +
					'<a href="#' + ThumbCount + '" title="<?=$remove_title?>" class="delete"></a>' +
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

			var count = $(this).attr("href").substr(1);
			$(".image_attr_thumbs_" + count).remove();
			$(this).parents("ul").remove();
	
		// Add thumbnail of crops
		}).on("click",".image_attr .thumbnail",function(ev) {
			ev.preventDefault();

			var count = $(this).attr("href").substr(1);
			CropThumbCount++;			
			$(this).parents("ul").after('<ul class="require_width_or_height image_attr_thumbs_' + count + '">' +
				'<li class="thumbed">' +
					'<span class="icon_small icon_small_picture" title="<?=$thumb_title?>"></span>' +
					'<input type="text" class="image_attr_thumbs" name="crops[' + count + '][thumbs][' + CropThumbCount + '][prefix]" value="" />' +
				'</li>' +
				'<li><input type="text" name="crops[' + count + '][thumbs][' + CropThumbCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="crops[' + count + '][thumbs][' + CropThumbCount + '][height]" value="" /></li>' +
				'<li class="actions">' +
					'<span class="icon_small icon_small_up"></span>' +
					'<input type="hidden" name="crops[' + count + '][thumbs][' + CropThumbCount + '][grayscale]" value="" />' +
					'<a href="#" title="<?=$color_mode_title?>" class="color_mode"></a>' +
					'<a href="#" title="<?=$remove_title?>" class="delete"></a>' +
				'</li></ul>');

		// Add sub-crop of crops
		}).on("click",".image_attr .subcrop",function(ev) {
			ev.preventDefault();

			var count = $(this).attr("href").substr(1);
			CropSubCount++;			
			$(this).parents("ul").after('<ul class="require_width_or_height image_attr_thumbs_' + count + '">' +
				'<li class="thumbed">' +
					'<span class="icon_small icon_small_crop" title="<?=$subcrop_title?>"></span>' +
					'<input type="text" class="image_attr_thumbs" name="crops[' + count + '][center_crops][' + CropSubCount + '][prefix]" value="" />' +
				'</li>' +
				'<li><input type="text" name="crops[' + count + '][center_crops][' + CropSubCount + '][width]" value="" /></li>' +
				'<li><input type="text" name="crops[' + count + '][center_crops][' + CropSubCount + '][height]" value="" /></li>' +
				'<li class="actions">' +
					'<span class="icon_small icon_small_up"></span>' +
					'<input type="hidden" name="crops[' + count + '][center_crops][' + CropSubCount + '][grayscale]" value="" />' +
					'<a href="#" title="<?=$color_mode_title?>" class="color_mode"></a>' +
					'<a href="#" title="<?=$remove_title?>" class="delete"></a>' +
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
		}).on("keydown","#pop_crop_list input",function(ev) {
			if (ev.keyCode == 13) {
				ev.preventDefault();
				addCrop();
			}

		}).on("keydown","#pop_thumb_list input",function(ev) {
			if (ev.keyCode == 13) {
				ev.preventDefault();
				addThumb();
			}

		// Hook Add Buttons
		}).on("click",".add_crop",addCrop).on("click",".add_thumb",addThumb).on("click",".add_center_crop",addCenterCrop);

		// Preset choosing
		$("#preset_select").change(function() {
			if ($(this).val()) {
				OptionsContainer.load("<?=ADMIN_ROOT?>ajax/developer/field-options/_image-preset/", { id: $(this).val() }, function() {
					BigTreeCustomControls();
				});
			} else {
				OptionsContainer.find("input").prop("disabled",false);
				OptionsContainer.find(".disabled").removeClass("disabled");
				OptionsContainer.find(".add_crop, .add_thumb").show();
			}
		});

		// Make sure a height and width is entered for each crop
		$(".bigtree_dialog_form").submit(function(ev) {
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