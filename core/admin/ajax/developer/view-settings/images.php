<?php
	$draggable = isset($settings["draggable"]) ? $settings["draggable"] : "";
	$prefix = isset($settings["prefix"]) ? $settings["prefix"] : "";
	$image = isset($settings["image"]) ? $settings["image"] : "";
	$sort = isset($settings["sort"]) ? $settings["sort"] : "DESC";
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($prefix)?>" />
</fieldset>

<fieldset>
	<label>Image Field</label>
	<?php if ($table) { ?>
	<select name="image">
		<?php BigTree::getFieldSelectOptions($table,$image) ?>
	</select>
	<?php } else { ?>
	<input name="image" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<?php } ?>
</fieldset>

<fieldset>
	<label>Sort Direction<small>(if not draggable)</small></label>
	<select name="sort">
		<option value="DESC">Newest First</option>
		<option value="ASC"<?php if ($sort == "ASC") { ?> selected="selected"<?php } ?>>Oldest First</option>
	</select>
</fieldset>