<?php
	// Prevent Notices
	$data['list_type'] = isset($data['list_type']) ? $data['list_type'] : '';
	$data['allow-empty'] = isset($data['allow-empty']) ? $data['allow-empty'] : '';
	$data['pop-table'] = isset($data['pop-table']) ? $data['pop-table'] : '';
	$data['pop-description'] = isset($data['pop-description']) ? $data['pop-description'] : '';
	$data['pop-sort'] = isset($data['pop-sort']) ? $data['pop-sort'] : '';
	$data['list'] = isset($data['list']) ? $data['list'] : array();

	$types = array(
		'static' => 'Static',
		'db' => 'Database Populated',
		'state' => 'State List',
		'country' => 'Country List',
	);
?>
<fieldset>
	<label>List Type</label>
	<select name="list_type" id="field_list_types">
		<?php foreach ($types as $val => $desc) {
    ?>
		<option value="<?=$val?>"<?php if ($val == $data['list_type']) {
    ?> selected="selected"<?php 
}
    ?>><?=$desc?></option>
		<?php 
} ?>
	</select>
</fieldset>
<fieldset>
	<label>Allow Empty <small>(first option is blank)</small></label>
	<select name="allow-empty">
		<option value="Yes">Yes</option>
		<option value="No"<?php if ($data['allow-empty'] == 'No') {
    ?> selected="selected"<?php 
} ?>>No</option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<?php if ($data['list_type'] && $data['list_type'] != 'static') {
    ?> style="display: none;"<?php 
} ?>></div>

<div class="list_type_options" id="db_list_options"<?php if ($data['list_type'] != 'db') {
    ?> style="display: none;"<?php 
} ?>>
	<h4>Database Populated List Options</h4>
	<fieldset>
		<label>Table</label>
		<select name="pop-table" class="table_select">
			<option></option>
			<?php BigTree::getTableSelectOptions($data['pop-table']); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label>Description Field</label>
		<div data-name="pop-description" class="pop-dependant pop-table">
			<?php if ($data['pop-table']) {
    ?>
			<select name="pop-description"><?php BigTree::getFieldSelectOptions($data['pop-table'], $data['pop-description']) ?></select>
			<?php 
} else {
    ?>
			<input type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
			<?php 
} ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label>Sort By</label>
		<div data-name="pop-sort" class="sort_by pop-dependant pop-table">
			<?php if ($data['pop-table']) {
    ?>
			<select name="pop-sort"><?php BigTree::getFieldSelectOptions($data['pop-table'], $data['pop-sort'], true) ?></select>
			<?php 
} else {
    ?>
			<input type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
			<?php 
} ?>
		</div>	
	</fieldset>
	<br />
</div>

<script>	
	$("#field_list_types").change(function() {
		$(".list_type_options").hide();
		if ($(this).val() == "static") {
			$("#static_list_options").show();
		} else if ($(this).val() == "db") {
			$("#db_list_options").show();
		}
	});
	var localList = BigTreeListMaker({
		element: "#static_list_options",
		name: "list",
		title: "Static List Options",
		columns: ["Value","Description"],
		keys: [{ key: "value", type: "text" },{ key: "description", type: "text" }],
		existing: <?=json_encode($data['list'])?>
	});
</script>