<?php
	namespace BigTree;

	$draggable = isset($options["draggable"]) ? $options["draggable"] : "";
	$prefix = isset($options["prefix"]) ? $options["prefix"] : "";
	$image = isset($options["image"]) ? $options["image"] : "";
	$sort = isset($options["sort"]) ? $options["sort"] : "DESC";
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label class="for_checkbox"><?=Text::translate("Draggable")?></label>
</fieldset>

<fieldset>
	<label><?=Text::translate("Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small>")?></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($prefix)?>" />
</fieldset>

<fieldset>
	<label><?=Text::translate("Image Field")?></label>
	<?php if ($table) { ?>
	<select name="image">
		<?php \BigTree::getFieldSelectOptions($table,$image) ?>
	</select>
	<?php } else { ?>
	<input name="image" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label><?=Text::translate("Sort Direction<small>(if not draggable)</small>")?></label>
	<select name="sort">
		<option value="DESC"><?=Text::translate("Newest First")?></option>
		<option value="ASC"<?php if ($sort == "ASC") { ?> selected="selected"<?php } ?>><?=Text::translate("Oldest First")?></option>
	</select>
</fieldset>