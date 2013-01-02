<?
	// Stop notices
	$data["directory"] = isset($data["directory"]) ? $data["directory"] : "";
	$data["image"] = isset($data["image"]) ? $data["image"] : "";
	$data["min_width"] = isset($data["min_width"]) ? $data["min_width"] : "";
	$data["min_height"] = isset($data["min_height"]) ? $data["min_height"] : "";
	$data["preview_prefix"] = isset($data["preview_prefix"]) ? $data["preview_prefix"] : "";
	$data["crops"] = isset($data["crops"]) ? $data["crops"] : "";
	$data["thumbs"] = isset($data["thumbs"]) ? $data["thumbs"] : "";
	
	if (!isset($_POST["template"])) {
?>
<fieldset>
	<label>Upload Directory <small>(required)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($data["directory"])?>" />
</fieldset>
<?
	}
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
	<label>Create Hi-Resolution Retina Images <small><a href="http://www.bigtreecms.org/documentation/developer-guide/field-types/retina-images/" target="_blank">(learn more)</a></small></label>
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
			$ctx = 0;
			$cx = 0;
			if (!empty($data["crops"])) {
				foreach ($data["crops"] as $crop) {
		?>
		<ul>
			<li>
				<input type="text" name="crops[<?=$cx?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" />
			</li>
			<li>
				<input type="text" name="crops[<?=$cx?>][width]" value="<?=htmlspecialchars($crop["width"])?>" />
			</li>
			<li>
				<input type="text" name="crops[<?=$cx?>][height]" value="<?=htmlspecialchars($crop["height"])?>" />
			</li>
			<li class="thumbnail"><a href="#<?=$cx?>" title="Create Thumbnail of Crop"></a></li>
			<li class="colormode">
				<input type="hidden" name="crops[<?=$cx?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="Switch Color Mode"<? if ($crop["grayscale"]) { ?> class="gray"<? } ?>></a>
			</li>
			<li class="del"><a href="#<?=$cx?>" title="Remove"></a></li>
		</ul>
		<?
					if (!empty($crop["thumbs"])) {
						foreach ($crop["thumbs"] as $thumb) {
							$ctx++;
		?>
		<ul class="image_attr_thumbs_<?=$cx?>">
			<li class="thumbed">
				<span class="icon_small icon_small_picture"></span>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" />
			</li>
			<li>
				<input type="text" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" />
			</li>
			<li>
				<input type="text" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" />
			</li>
			<li class="up"><span class="icon_small icon_small_up"></span></li>
			<li class="colormode">
				<input type="hidden" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode"<? if ($thumb["grayscale"]) { ?> class="gray"<? } ?>></a>
			</li>
			<li class="del"><a href="#" title="Remove"></a></li>
		</ul>
		<?		
						}
					}
					$cx++;
				}
			}
		?>
	</div>
</fieldset>

<h4>Thumbnails <a href="#" class="add_thumb icon_small icon_small_add"></a></h4>
<fieldset>
	<div class="image_attr" id="pop_thumb_list">
		<ul>
			<li>Prefix:</li><li>Width:</li><li>Height:</li>
		</ul>
		<?
			$tx = 0;
			if (!empty($data["thumbs"])) {
				foreach ($data["thumbs"] as $thumb) {
		?>
		<ul>
			<li>
				<input type="text" name="thumbs[<?=$tx?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" />
			</li>
			<li>
				<input type="text" name="thumbs[<?=$tx?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" />
			</li>
			<li>
				<input type="text" name="thumbs[<?=$tx?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" />
			</li>
			<li class="colormode">
				<input type="hidden" name="thumbs[<?=$tx?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="Switch Color Mode"<? if ($thumb["grayscale"]) { ?> class="gray"<? } ?>></a>
			</li>
			<li class="del"><a href="#" title="Remove"></a></li>
		</ul>
		<?
					$tx++;
				}
			}
		?>
	</div>
</fieldset>

<? include BigTree::path("admin/ajax/developer/field-options/_photo-common-js.php") ?>