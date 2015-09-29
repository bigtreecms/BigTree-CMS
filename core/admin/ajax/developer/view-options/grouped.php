<?php
	// Defaults
	$sort = isset($options['sort']) ? $options['sort'] : 'id DESC';
	$group_field = isset($options['group_field']) ? $options['group_field'] : '';
	$per_page = isset($options['per_page']) ? $options['per_page'] : 15;
	$draggable = isset($options['draggable']) ? $options['draggable'] : false;
	$other_table = isset($options['other_table']) ? $options['other_table'] : false;
	$title_field = isset($options['title_field']) ? $options['title_field'] : false;
	$ot_sort_field = isset($options['ot_sort_field']) ? $options['ot_sort_field'] : false;
	$ot_sort_direction = isset($options['ot_sort_direction']) ? $options['ot_sort_direction'] : false;
	$group_parser = isset($options['group_parser']) ? $options['group_parser'] : '';
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) {
    ?>checked="checked" <?php 
} ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Group Field</label>
	<?php if ($table) {
    ?>
	<select name="group_field">
		<?php BigTree::getFieldSelectOptions($table, $group_field) ?>
	</select>
	<?php 
} else {
    ?>
	<input name="group_field" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<?php 
} ?>
</fieldset>

<fieldset>
	<label>Sort By Inside Groups <small>(if not draggable)</small></label>
	<?php if ($table) {
    ?>
	<select name="sort">
		<?php BigTree::getFieldSelectOptions($table, $sort, true) ?>
	</select>
	<?php 
} else {
    ?>
	<input name="sort" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<?php 
} ?>
</fieldset>

<h4>Grouping Parameters</h4>

<fieldset>
	<label>Other Table</label>
	<select name="other_table" class="table_select">
		<option></option>
		<?php BigTree::getTableSelectOptions($other_table) ?>
	</select>
</fieldset>

<fieldset>
	<label>Field to Pull for Title</label>
	<div data-name="title_field">
		<?php if ($other_table) {
    ?>
		<select name="title_field">
			<?php BigTree::getFieldSelectOptions($other_table, $title_field) ?>
		</select>
		<?php 
} else {
    ?>
		<input type="text" disabled="disabled" value="Please select &quot;Other Table&quot;" />
		<?php 
} ?>
	</div>
</fieldset>

<fieldset>
	<label>Field to Sort By</label>
	<div data-name="ot_sort_field">
		<?php if ($other_table) {
    ?>
		<select name="ot_sort_field">
			<?php BigTree::getFieldSelectOptions($other_table, $ot_sort_field) ?>
		</select>
		<?php 
} else {
    ?>
		<input type="text" disabled="disabled" value="Please select &quot;Other Table&quot;" />
		<?php 
} ?>
	</div>
</fieldset>

<fieldset>
	<label>Sort Direction</label>
	<select name="ot_sort_direction">
		<option>ASC</option>
		<option<?php if ($ot_sort_direction == 'DESC') {
    ?> selected="selected"<?php 
} ?>>DESC</option>
	</select>
</fieldset>

<fieldset>
	<label>Group Name Parser <small>($item is the group data, set $value to the new name)</small></label>
	<textarea name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>