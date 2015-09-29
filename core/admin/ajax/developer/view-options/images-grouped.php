<?php
	$draggable = isset($options['draggable']) ? $options['draggable'] : '';
	$prefix = isset($options['prefix']) ? $options['prefix'] : '';
	$image = isset($options['image']) ? $options['image'] : '';
	$group_field = isset($options['group_field']) ? $options['group_field'] : '';
	$other_table = isset($options['other_table']) ? $options['other_table'] : '';
	$title_field = isset($options['title_field']) ? $options['title_field'] : '';
	$group_parser = isset($options['group_parser']) ? $options['group_parser'] : '';
	$sort = isset($options['sort']) ? $options['sort'] : 'DESC';	
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) {
    ?>checked="checked" <?php 
} ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($prefix)?>" />
</fieldset>

<fieldset>
	<label>Image Field</label>
	<?php if ($table) {
    ?>
	<select name="image">
		<?php BigTree::getFieldSelectOptions($table, $image) ?>
	</select>
	<?php 
} else {
    ?>
	<input name="image" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<?php 
} ?>
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
	<label>Sort Direction<small>(inside groups, if not draggable)</small></label>
	<select name="sort">
		<option value="DESC">Newest First</option>
		<option value="ASC"<?php if ($sort == 'ASC') {
    ?> selected="selected"<?php 
} ?>>Oldest First</option>
	</select>
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
	<label>Group Name Parser <small>($item is the group data, set $value to the new name)</small></label>
	<textarea name="group_parser"><?=htmlspecialchars($group_parser)?></textarea>
</fieldset>