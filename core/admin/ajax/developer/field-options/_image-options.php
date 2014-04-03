<?
	$data["min_width"] = isset($data["min_width"]) ? $data["min_width"] : "";
	$data["min_height"] = isset($data["min_height"]) ? $data["min_height"] : "";
	$data["preview_prefix"] = isset($data["preview_prefix"]) ? $data["preview_prefix"] : "";
	$data["crops"] = isset($data["crops"]) ? $data["crops"] : array();
	$data["thumbs"] = isset($data["thumbs"]) ? $data["thumbs"] : array();
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
			<li class="thumbnail"><a href="#<?=$crop_count?>" title="Create Thumbnail of Crop"></a></li>
			<li class="colormode">
				<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode"<? if ($crop["grayscale"]) { ?> class="gray"<? } ?>></a>
			</li>
			<li class="del"><a href="#<?=$crop_count?>" title="Remove"></a></li>
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
			<li class="up"><span class="icon_small icon_small_up"></span></li>
			<li class="colormode">
				<input type="hidden" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode"<? if ($thumb["grayscale"]) { ?> class="gray"<? } ?>></a>
			</li>
			<li class="del"><a href="#" title="Remove"></a></li>
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
			<li class="colormode">
				<input type="hidden" name="thumbs[<?=$thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode"<? if ($thumb["grayscale"]) { ?> class="gray"<? } ?>></a>
			</li>
			<li class="del"><a href="#" title="Remove"></a></li>
		</ul>
		<?
					}
				}
			}
		?>
	</div>
</fieldset>

<script>
	BigTree.cropCount = <?=$crop_count?>;
	BigTree.thumbCount = <?=$thumb_count?>;
	BigTree.cropThumbCount = <?=$crop_thumb_count?>;
	
	// Remove a thumbnail or crop
	$(".image_attr").on("click",".del a",function() {
		var count = $(this).attr("href").substr(1);
		$(".image_attr_thumbs_" + count).remove();
		$(this).parents("ul").remove();
		
		return false;
	});
	
	// Add thumbnail of crops
	$(".image_attr").on("click",".thumbnail a",function() {
		var count = $(this).attr("href").substr(1);
		BigTree.cropThumbCount++;
		
		$(this).parents("ul").after('<ul class="require_width_or_height image_attr_thumbs_' + count + '"><li class="thumbed"><span class="icon_small icon_small_picture" title="Thumbnail"></span><input type="text" class="image_attr_thumbs" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][prefix]" value="" /></li><li><input type="text" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][width]" value="" /></li><li><input type="text" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][height]" value="" /></li><li class="up"><span class="icon_small icon_small_up"></span></li><li class="colormode"><input type="hidden" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][grayscale]" value="" /><a href="#" title="Switch Color Mode"></a></li><li class="del"><a href="#" title="Remove"></a></li></ul>');
		
		return false;
	});
	
	// Switch between color and grayscale
	$(".image_attr").on("click",".colormode a",function() {
		$(this).toggleClass("gray");
		if ($(this).hasClass("gray")) {
			$(this).prev("input").val("on");
		} else {
			$(this).prev("input").val("");			
		}
		
		return false;
	});

	BigTree.localAddCrop = function() {
		BigTree.cropCount++;
		$("#pop_crop_list").append('<ul><li><input type="text" name="crops[' + BigTree.cropCount + '][prefix]" value="" /></li><li><input type="text" class="required" name="crops[' + BigTree.cropCount + '][width]" value="" /></li><li><input type="text" class="required" name="crops[' + BigTree.cropCount + '][height]" value="" /></li><li class="thumbnail"><a href="#' + BigTree.cropCount + '" title="Create Thumbnail of Crop"></a></li><li class="colormode"><input type="hidden" name="crops[' + BigTree.cropCount + '][grayscale]" value="" /><a href="#" title="Switch Color Mode"></a></li><li class="del"><a href="#' + BigTree.cropCount + '" title="Remove"></a></li></ul>');
		
		return false;
	};

	BigTree.localAddThumb = function() {
		BigTree.thumbCount++;
		$("#pop_thumb_list").append('<ul class="require_width_or_height"><li><input type="text" name="thumbs[' + BigTree.thumbCount + '][prefix]" value="" /><li><input type="text" name="thumbs[' + BigTree.thumbCount + '][width]" value="" /><li><input type="text" name="thumbs[' + BigTree.thumbCount + '][height]" value="" /></li><li class="colormode"><input type="hidden" name="thumbs[' + BigTree.thumbCount + '][grayscale]" value="" /><a href="#" title="Switch Color Mode"></a></li><li class="del"><a href="#"></a></li></ul>');
		
		return false;
	}

	// Allow you to hit enter in a crop/thumb box to create another automatically
	$("#pop_crop_list input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			BigTree.localAddCrop();
			return false;
		}
	});	
	$("#pop_thumb_list input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			BigTree.localAddThumb();
			return false;
		}
	});

	// Hook Add Buttons
	$(".add_crop").click(BigTree.localAddCrop);
	$(".add_thumb").click(BigTree.localAddThumb);

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
	})
</script>