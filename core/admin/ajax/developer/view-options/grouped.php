<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 * @global string $table
	 */

	// Defaults
	$sort = isset($options["sort"]) ? $options["sort"] : "id DESC";
	$group_field = isset($options["group_field"]) ? $options["group_field"] : "";
	$draggable = isset($options["draggable"]) ? $options["draggable"] : false;
	$other_table = isset($options["other_table"]) ? $options["other_table"] : false;
	$title_field = isset($options["title_field"]) ? $options["title_field"] : false;
	$ot_sort_field = isset($options["ot_sort_field"]) ? $options["ot_sort_field"] : false;
	$ot_sort_direction = isset($options["ot_sort_direction"]) ? $options["ot_sort_direction"] : false;
	$group_parser = isset($options["group_parser"]) ? $options["group_parser"] : "";
?>
<fieldset>
	<input id="options_field_draggable" type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label for="options_field_draggable" class="for_checkbox"><?=Text::translate("Draggable")?></label>
</fieldset>

<fieldset>
	<label for="options_field_group_field"><?=Text::translate("Group Field")?></label>
	<?php if ($table) { ?>
	<select id="options_field_group_field" name="group_field">
		<?php SQL::drawColumnSelectOptions($table,$group_field) ?>
	</select>
	<?php } else { ?>
	<input id="options_field_group_field" name="group_field" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label for="options_field_sortby"><?=Text::translate("Sort By Inside Groups <small>(if not draggable)</small>")?></label>
	<?php if ($table) { ?>
	<select id="options_field_sortby" name="sort">
		<?php SQL::drawColumnSelectOptions($table,$sort,true) ?>
	</select>
	<?php } else { ?>
	<input id="options_field_sortby" name="sort" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<h4><?=Text::translate("Grouping Parameters")?></h4>

<fieldset>
	<label for="options_field_other_table"><?=Text::translate("Other Table")?></label>
	<select id="options_field_other_table" name="other_table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($other_table) ?>
	</select>
</fieldset>

<fieldset>
	<label for="options_field_title_field"><?=Text::translate("Field to Pull for Title")?></label>
	<div data-name="title_field">
		<?php if ($other_table) { ?>
		<select id="options_field_title_field" name="title_field">
			<?php SQL::drawColumnSelectOptions($other_table,$title_field) ?>
		</select>
		<?php } else { ?>
		<input id="options_field_title_field" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_sort_field"><?=Text::translate("Field to Sort By")?></label>
	<div data-name="ot_sort_field">
		<?php if ($other_table) { ?>
		<select id="options_field_sort_field" name="ot_sort_field">
			<?php SQL::drawColumnSelectOptions($other_table,$ot_sort_field) ?>
		</select>
		<?php } else { ?>
		<input id="options_field_sort_field" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_sort_direction"><?=Text::translate("Sort Direction")?></label>
	<select id="options_field_sort_direction" name="ot_sort_direction">
		<option>ASC</option>
		<option<?php if ($ot_sort_direction == "DESC") { ?> selected="selected"<?php } ?>>DESC</option>
	</select>
</fieldset>

<fieldset>
	<label for="options_field_group_parser"><?=Text::translate('Group Name Parser <small>($item is the group data, set $value to the new name)</small>')?></label>
	<textarea id="options_field_group_parser" name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>