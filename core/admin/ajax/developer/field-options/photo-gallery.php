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

<h4>Crops <a href="#" class="add_crop"><img src="<?=ADMIN_ROOT?>images/add.png" alt="" /></a></h4>
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
			<li class="thumbnail"><a href="#<?=$cx?>"></a></li>
			<li class="del"><a href="#<?=$cx?>"></a></li>
		</ul>
		<?
					if (!empty($crop["thumbs"])) {
						foreach ($crop["thumbs"] as $thumb) {
							$ctx++;
		?>
		<ul class="image_attr_thumbs_<?=$cx?>">
			<li>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" />
			</li>
			<li>
				<input type="text" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" />
			</li>
			<li>
				<input type="text" name="crops[<?=$cx?>][thumbs][<?=$ctx?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" />
			</li>
			<li class="up"></li>
			<li class="del"><a href="#"></a></li>
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

<h4>Thumbnails <a href="#" class="add_thumb"><img src="<?=ADMIN_ROOT?>images/add.png" alt="" /></a></h4>
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
			<li class="del"><a href="#"></a></li>
		</ul>
		<?
					$tx++;
				}
			}
		?>
	</div>
</fieldset>

<? include BigTree::path("admin/ajax/developer/field-options/_photo-common-js.php") ?>