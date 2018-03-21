<?php
	// Prevent Notices
	$settings["list_type"] = isset($settings["list_type"]) ? $settings["list_type"] : "";
	$settings["allow-empty"] = isset($settings["allow-empty"]) ? $settings["allow-empty"] : "";
	$settings["pop-table"] = isset($settings["pop-table"]) ? $settings["pop-table"] : "";
	$settings["pop-description"] = isset($settings["pop-description"]) ? $settings["pop-description"] : "";
	$settings["pop-sort"] = isset($settings["pop-sort"]) ? $settings["pop-sort"] : "";
	$settings["list"] = isset($settings["list"]) ? $settings["list"] : array();
	
	$types = array(
		"static" => "Static",
		"db" => "Database Populated",
		"state" => "State List",
		"country" => "Country List"
	);
?>
<fieldset>
	<label for="field_list_types">List Type</label>
	<select name="list_type" id="field_list_types">
		<?php foreach ($types as $val => $desc) { ?>
		<option value="<?=$val?>"<?php if ($val == $settings["list_type"]) { ?> selected="selected"<?php } ?>><?=$desc?></option>
		<?php } ?>
	</select>
</fieldset>
<fieldset>
	<label for="settings_field_allow_empty">Allow Empty <small>(first option is blank)</small></label>
	<select id="settings_field_allow_empty" name="allow-empty">
		<option value="Yes">Yes</option>
		<option value="No"<?php if ($settings["allow-empty"] == "No") { ?> selected="selected"<?php } ?>>No</option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<?php if ($settings["list_type"] && $settings["list_type"] != "static") { ?> style="display: none;"<?php } ?>></div>

<div class="list_type_options" id="db_list_options"<?php if ($settings["list_type"] != "db") { ?> style="display: none;"<?php } ?>>
	<h4>Database Populated List Options</h4>
	<fieldset>
		<label for="settings_field_pop_table">Table</label>
		<select id="settings_field_pop_table" name="pop-table" class="table_select">
			<option></option>
			<?php BigTree::getTableSelectOptions($settings["pop-table"]); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label for="settings_field_desc_field">Description Field</label>
		<div data-name="pop-description" class="pop-dependant pop-table">
			<?php if ($settings["pop-table"]) { ?>
			<select id="settings_field_desc_field" name="pop-description"><?php BigTree::getFieldSelectOptions($settings["pop-table"],$settings["pop-description"]) ?></select>
			<?php } else { ?>
			<input id="settings_field_desc_field" type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
			<?php } ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label for="settings_field_sort_by">Sort By</label>
		<div data-name="pop-sort" class="sort_by pop-dependant pop-table">
			<?php if ($settings["pop-table"]) { ?>
			<select id="settings_field_sort_by" name="pop-sort"><?php BigTree::getFieldSelectOptions($settings["pop-table"],$settings["pop-sort"],true) ?></select>
			<?php } else { ?>
			<input id="settings_field_sort_by" type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
			<?php } ?>
		</div>	
	</fieldset>

	<fieldset>
		<label for="settings_field_list_parser">List Parser Function</label>
		<input id="settings_field_list_parser" type="text" name="parser" value="<?=htmlspecialchars($settings["parser"])?>" />
		<p class="note">Your function will receive an array of the available entries and should return a modified array.</p>
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
		existing: <?=json_encode($settings["list"])?>
	});
</script>