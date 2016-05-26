<?php
	namespace BigTree;

	// Defaults
	$sort = isset($options["sort"]) ? $options["sort"] : "id DESC";
	$group_field = isset($options["group_field"]) ? $options["group_field"] : "";
	$per_page = isset($options["per_page"]) ? $options["per_page"] : 15;
	$draggable = isset($options["draggable"]) ? $options["draggable"] : false;
	$other_table = isset($options["other_table"]) ? $options["other_table"] : false;
	$title_field = isset($options["title_field"]) ? $options["title_field"] : false;
	$ot_sort_field = isset($options["ot_sort_field"]) ? $options["ot_sort_field"] : false;
	$ot_sort_direction = isset($options["ot_sort_direction"]) ? $options["ot_sort_direction"] : false;
	$group_parser = isset($options["group_parser"]) ? $options["group_parser"] : "";
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label class="for_checkbox"><?=Text::translate("Draggable")?></label>
</fieldset>

<fieldset>
	<label><?=Text::translate("Group Field")?></label>
	<?php if ($table) { ?>
	<select name="group_field">
		<?php SQL::drawColumnSelectOptions($table,$group_field) ?>
	</select>
	<?php } else { ?>
	<input name="group_field" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label><?=Text::translate("Sort By Inside Groups <small>(if not draggable)</small>")?></label>
	<?php if ($table) { ?>
	<select name="sort">
		<?php SQL::drawColumnSelectOptions($table,$sort,true) ?>
	</select>
	<?php } else { ?>
	<input name="sort" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<h4><?=Text::translate("Grouping Parameters")?></h4>

<fieldset>
	<label><?=Text::translate("Other Table")?></label>
	<select name="other_table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($other_table) ?>
	</select>
</fieldset>

<fieldset>
	<label><?=Text::translate("Field to Pull for Title")?></label>
	<div data-name="title_field">
		<?php if ($other_table) { ?>
		<select name="title_field">
			<?php SQL::drawColumnSelectOptions($other_table,$title_field) ?>
		</select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label><?=Text::translate("Field to Sort By")?></label>
	<div data-name="ot_sort_field">
		<?php if ($other_table) { ?>
		<select name="ot_sort_field">
			<?php SQL::drawColumnSelectOptions($other_table,$ot_sort_field) ?>
		</select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label><?=Text::translate("Sort Direction")?></label>
	<select name="ot_sort_direction">
		<option>ASC</option>
		<option<?php if ($ot_sort_direction == "DESC") { ?> selected="selected"<?php } ?>>DESC</option>
	</select>
</fieldset>

<fieldset>
	<label><?=Text::translate('Group Name Parser <small>($item is the group data, set $value to the new name)</small>')?></label>
	<textarea name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>