<? if (!isset($_POST["template"])) { ?>
<fieldset>
	<label>Upload Directory <small>(required)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($d["directory"])?>" />
</fieldset>
<? } ?>
<fieldset>
	<label>Minimum Width <small>(numeric value in pixels)</small></label>
	<input type="text" name="min_width" value="<?=htmlspecialchars($d["min_width"])?>" />
</fieldset>
<fieldset>
	<label>Minimum Height <small>(numeric value in pixels)</small></label>
	<input type="text" name="min_height" value="<?=htmlspecialchars($d["min_height"])?>" />
</fieldset>
<fieldset>
	<label>Preview Prefix <small>(for forms)</small></label>
	<input type="text" name="preview_prefix" value="<?=htmlspecialchars($d["preview_prefix"])?>" />
</fieldset>

<h4>Crops <a href="#" class="add_crop"><img src="<?=$admin_root?>images/add.png" alt="" /></a></h4>
<fieldset>
	<div class="image_attr" id="pop_crop_list">
		<ul>
			<li>Prefix:</li><li>Width:</li><li>Height:</li>
		</ul>
		<?
			$ctx = 0;
			$cx = 0;
			if (!empty($d["crops"])) {
				foreach ($d["crops"] as $crop) {
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

<h4>Thumbnails <a href="#" class="add_thumb"><img src="<?=$admin_root?>images/add.png" alt="" /></a></h4>
<fieldset>
	<div class="image_attr" id="pop_thumb_list">
		<ul>
			<li>Prefix:</li><li>Width:</li><li>Height:</li>
		</ul>
		<?
			$tx = 0;
			if (!empty($d["thumbs"])) {
				foreach ($d["thumbs"] as $thumb) {
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