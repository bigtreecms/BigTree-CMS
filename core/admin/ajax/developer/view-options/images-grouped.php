<?php
	namespace BigTree;

	$draggable = isset($options["draggable"]) ? $options["draggable"] : "";
	$prefix = isset($options["prefix"]) ? $options["prefix"] : "";
	$image = isset($options["image"]) ? $options["image"] : "";
	$group_field = isset($options["group_field"]) ? $options["group_field"] : "";
	$other_table = isset($options["other_table"]) ? $options["other_table"] : "";
	$title_field = isset($options["title_field"]) ? $options["title_field"] : "";
	$group_parser = isset($options["group_parser"]) ? $options["group_parser"] : "";
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
	<label><?=Text::translate("Group Field")?></label>
	<?php if ($table) { ?>
	<select name="group_field">
		<?php BigTree::getFieldSelectOptions($table,$group_field) ?>
	</select>
	<?php } else { ?>
	<input name="group_field" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label><?=Text::translate("Sort Direction<small>(inside groups, if not draggable)</small>")?></label>
	<select name="sort">
		<option value="DESC"><?=Text::translate("Newest First")?></option>
		<option value="ASC"<?php if ($sort == "ASC") { ?> selected="selected"<?php } ?>><?=Text::translate("Oldest First")?></option>
	</select>
</fieldset>

<h4><?=Text::translate("Grouping Parameters")?></h4>

<fieldset>
	<label><?=Text::translate("Other Table")?></label>
	<select name="other_table" class="table_select">
		<option></option>
		<?php \BigTree::getTableSelectOptions($other_table) ?>
	</select>
</fieldset>

<fieldset>
	<label><?=Text::translate("Field to Pull for Title")?></label>
	<div data-name="title_field">
		<?php if ($other_table) { ?>
		<select name="title_field">
			<?php \BigTree::getFieldSelectOptions($other_table,$title_field) ?>
		</select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label><?=Text::translate('Group Name Parser <small>($item is the group data, set $value to the new name)</small>')?></label>
	<textarea name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>