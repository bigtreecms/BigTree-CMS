<?
	// Prevent warnings
	$data = is_array($data) ? $data : array();

	$using_preset = false;
	$settings = BigTreeCMS::getSetting("bigtree-internal-media-settings");
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

	// We use this file for creating presets so we don't want to show the dropdown in that context
	if (!defined("BIGTREE_CREATING_PRESET") && array_filter((array)$settings["presets"])) {
?>
<fieldset>
	<label>Existing Preset</label>
	<select name="preset" id="preset_select">
		<option></option>
		<?
			foreach ($settings["presets"] as $preset) {
		?>
		<option value="<?=$preset["id"]?>"<? if ($preset["id"] == $data["preset"]) { ?> selected="selected"<? } ?>><?=$preset["name"]?></option>
		<?
			}
		?>
	</select>
</fieldset>
<?
	}
?>
<div id="image_options_container">
	<?
		if ($using_preset) {
			include "_image-preset.php";
		} else {
	?>
	<fieldset>
		<label>Minimum Width <small>(numeric value in pixels)</small></label>
		<input type="text" name="min_width" value="<?=htmlspecialchars($data["min_width"])?>" />
	</fieldset>
	<fieldset>
		<label>Minimum Height <small>(numeric value in pixels)</small></label>
		<input type="text" name="min_height" value="<?=htmlspecialchars($data["min_height"])?>" />
	</fieldset>
	<fieldset>
		<label>Preview Prefix <small>(for forms)</small></label>
		<input type="text" name="preview_prefix" value="<?=htmlspecialchars($data["preview_prefix"])?>" />
	</fieldset>
	<fieldset>
		<label>Create Hi-Resolution Retina Images <small><a href="http://www.bigtreecms.org/docs/dev-guide/field-types/retina-images/" target="_blank">(learn more)</a></small></label>
		<input type="checkbox" name="retina" <? if ($data["retina"]) { ?>checked="checked" <? } ?>/>
		<label class="for_checkbox"> When Available</label>
	</fieldset>
	
	<h4>Crops <a href="#" class="add_crop icon_small icon_small_add"></a></h4>
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
					<input type="text" name="crops[<?=$crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" />
				</li>
				<li class="actions">
					<a href="#<?=$crop_count?>" title="Create Centered Sub-Crop" class="subcrop"></a>
					<a href="#<?=$crop_count?>" title="Create Thumbnail of Crop" class="thumbnail"></a>
					<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
					<a href="#" title="Switch Color Mode" class="color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
					<a href="#<?=$crop_count?>" title="Remove" class="delete"></a>
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
					<a href="#" title="Switch Color Mode" class="color_mode<? if ($thumb["grayscale"]) { ?> gray<? } ?>"></a>
					<a href="#" title="Remove" class="delete"></a>
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
					<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" />
				</li>
				<li>
					<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" />
				</li>
				<li class="actions">
					<span class="icon_small icon_small_up"></span>
					<input type="hidden" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
					<a href="#" title="Switch Color Mode" class="color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
					<a href="#" title="Remove" class="delete"></a>
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
	
	<h4>Thumbnails <a href="#" class="add_thumb icon_small icon_small_add"></a></h4>
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
					<a href="#" title="Switch Color Mode" class="color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
					<a href="#<?=$thumb_count?>" title="Remove" class="delete"></a>
				</li>
			</ul>
			<?
						}
					}
				}
			?>
		</div>
	</fieldset>
	
	<h4>Center Crops <small>(automatically crops from the center of image)</small> <a href="#" class="add_center_crop icon_small icon_small_add"></a></h4>
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
					<a href="#" title="Switch Color Mode" class="color_mode<? if ($crop["grayscale"]) { ?> gray<? } ?>"></a>
					<a href="#<?=$center_crop_count?>" title="Remove" class="delete"></a>
				</li>
			</ul>
			<?
						}
					}
				}
			?>
		</div>
	</fieldset>
	<?
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
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#' + CenterCropCount + '" title="Remove" class="delete"></a>' +
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
					'<a href="#' + CropCount + '" title="Create Centered Sub-Crop" class="subcrop"></a>' +
					'<a href="#' + CropCount + '" title="Create Thumbnail of Crop" class="thumbnail"></a>' +
					'<input type="hidden" name="crops[' + CropCount + '][grayscale]" value="" />' +
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#' + CropCount + '" title="Remove" class="delete"></a>' +
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
					'<a href="#" title="Switch Color Mode" class="color_mode"></a>' +
					'<a href="#' + ThumbCount + '" title="Remove" class="delete"></a>' +
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
			$(this).parents("ul").after('<ul class="require_width_or_height image_attr_thumbs_' + count + '">' +
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