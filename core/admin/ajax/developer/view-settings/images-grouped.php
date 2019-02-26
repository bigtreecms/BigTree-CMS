<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 * @global string $table
	 */

	$draggable = isset($settings["draggable"]) ? $settings["draggable"] : "";
	$prefix = isset($settings["prefix"]) ? $settings["prefix"] : "";
	$image = isset($settings["image"]) ? $settings["image"] : "";
	$group_field = isset($settings["group_field"]) ? $settings["group_field"] : "";
	$other_table = isset($settings["other_table"]) ? $settings["other_table"] : "";
	$title_field = isset($settings["title_field"]) ? $settings["title_field"] : "";
	$group_parser = isset($settings["group_parser"]) ? $settings["group_parser"] : "";
	$sort = isset($settings["sort"]) ? $settings["sort"] : "DESC";	
?>
<fieldset>
	<input id="settings_field_draggable" type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label for="settings_field_draggable" class="for_checkbox"><?=Text::translate("Draggable")?></label>
</fieldset>

<fieldset>
	<label for="settings_field_prefix"><?=Text::translate("Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small>")?></label>
	<input id="settings_field_prefix" type="text" name="prefix" value="<?=htmlspecialchars($prefix)?>" />
</fieldset>

<fieldset>
	<label for="settings_field_image"><?=Text::translate("Image Field")?></label>
	<?php if ($table) { ?>
	<select id="settings_field_image" name="image">
		<?php SQL::drawColumnSelectOptions($table,$image) ?>
	</select>
	<?php } else { ?>
	<input id="settings_field_image" name="image" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label for="settings_field_group"><?=Text::translate("Group Field")?></label>
	<?php if ($table) { ?>
	<select id="settings_field_group" name="group_field">
		<?php SQL::drawColumnSelectOptions($table,$group_field) ?>
	</select>
	<?php } else { ?>
	<input id="settings_field_group" name="group_field" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label for="settings_field_sort"><?=Text::translate("Sort Direction<small>(inside groups, if not draggable)</small>")?></label>
	<select id="settings_field_sort" name="sort">
		<option value="DESC"><?=Text::translate("Newest First")?></option>
		<option value="ASC"<?php if ($sort == "ASC") { ?> selected="selected"<?php } ?>><?=Text::translate("Oldest First")?></option>
	</select>
</fieldset>

<h4><?=Text::translate("Grouping Parameters")?></h4>

<fieldset>
	<label for="settings_field_other_table"><?=Text::translate("Other Table")?></label>
	<select id="settings_field_other_table" name="other_table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($other_table) ?>
	</select>
</fieldset>

<fieldset>
	<label for="settings_field_title_field"><?=Text::translate("Field to Pull for Title")?></label>
	<div data-name="title_field">
		<?php if ($other_table) { ?>
		<select id="settings_field_title_field" name="title_field">
			<?php SQL::drawColumnSelectOptions($other_table,$title_field) ?>
		</select>
		<?php } else { ?>
		<input id="settings_field_title_field" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="settings_field_group_parser"><?=Text::translate('Group Name Parser <small>($item is the group data, set $value to the new name)</small>')?></label>
	<textarea id="settings_field_group_parser" name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>