<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 * @global string $table
	 */

	// Defaults
	$sort = isset($settings["sort"]) ? $settings["sort"] : "id DESC";
	$group_field = isset($settings["group_field"]) ? $settings["group_field"] : "";
	$draggable = isset($settings["draggable"]) ? $settings["draggable"] : false;
	$other_table = isset($settings["other_table"]) ? $settings["other_table"] : false;
	$title_field = isset($settings["title_field"]) ? $settings["title_field"] : false;
	$ot_sort_field = isset($settings["ot_sort_field"]) ? $settings["ot_sort_field"] : false;
	$ot_sort_direction = isset($settings["ot_sort_direction"]) ? $settings["ot_sort_direction"] : false;
	$group_parser = isset($settings["group_parser"]) ? $settings["group_parser"] : "";
?>
<fieldset>
	<input id="settings_field_draggable" type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label for="settings_field_draggable" class="for_checkbox"><?=Text::translate("Draggable")?></label>
</fieldset>

<fieldset>
	<label for="settings_field_group_field"><?=Text::translate("Group Field")?></label>
	<?php if ($table) { ?>
	<select id="settings_field_group_field" name="group_field">
		<?php SQL::drawColumnSelectOptions($table,$group_field) ?>
	</select>
	<?php } else { ?>
	<input id="settings_field_group_field" name="group_field" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label for="settings_field_sortby"><?=Text::translate("Sort By Inside Groups <small>(if not draggable)</small>")?></label>
	<?php if ($table) { ?>
	<select id="settings_field_sortby" name="sort">
		<?php SQL::drawColumnSelectOptions($table,$sort,true) ?>
	</select>
	<?php } else { ?>
	<input id="settings_field_sortby" name="sort" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
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
	<label for="settings_field_sort_field"><?=Text::translate("Field to Sort By")?></label>
	<div data-name="ot_sort_field">
		<?php if ($other_table) { ?>
		<select id="settings_field_sort_field" name="ot_sort_field">
			<?php SQL::drawColumnSelectOptions($other_table,$ot_sort_field) ?>
		</select>
		<?php } else { ?>
		<input id="settings_field_sort_field" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="settings_field_sort_direction"><?=Text::translate("Sort Direction")?></label>
	<select id="settings_field_sort_direction" name="ot_sort_direction">
		<option>ASC</option>
		<option<?php if ($ot_sort_direction == "DESC") { ?> selected="selected"<?php } ?>>DESC</option>
	</select>
</fieldset>

<fieldset>
	<label for="settings_field_group_parser"><?=Text::translate('Group Name Parser <small>($item is the group data, set $value to the new name)</small>')?></label>
	<textarea id="settings_field_group_parser" name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>