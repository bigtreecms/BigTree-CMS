<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Prevent Notices
	$options["list_type"] = isset($options["list_type"]) ? $options["list_type"] : "";
	$options["allow-empty"] = isset($options["allow-empty"]) ? $options["allow-empty"] : "";
	$options["pop-table"] = isset($options["pop-table"]) ? $options["pop-table"] : "";
	$options["pop-description"] = isset($options["pop-description"]) ? $options["pop-description"] : "";
	$options["pop-sort"] = isset($options["pop-sort"]) ? $options["pop-sort"] : "";
	$options["list"] = isset($options["list"]) ? $options["list"] : array();
	
	$types = array(
		"static" => Text::translate("Static"),
		"db" => Text::translate("Database Populated"),
		"state" => Text::translate("State List"),
		"country" => Text::translate("Country List")
	);
?>
<fieldset>
	<label for="options_field_type"><?=Text::translate("List Type")?></label>
	<select id="options_field_type" name="list_type" id="field_list_types">
		<?php foreach ($types as $val => $desc) { ?>
		<option value="<?=$val?>"<?php if ($val == $options["list_type"]) { ?> selected="selected"<?php } ?>><?=$desc?></option>
		<?php } ?>
	</select>
</fieldset>

<fieldset>
	<label for="options_field_empty"><?=Text::translate("Allow Empty <small>(first option is blank)</small>")?></label>
	<select id="options_field_empty" name="allow-empty">
		<option value="Yes"><?=Text::translate("Yes")?></option>
		<option value="No"<?php if ($options["allow-empty"] == "No") { ?> selected="selected"<?php } ?>><?=Text::translate("No")?></option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<?php if ($options["list_type"] && $options["list_type"] != "static") { ?> style="display: none;"<?php } ?>>
</div>

<div class="list_type_options" id="db_list_options"<?php if ($options["list_type"] != "db") { ?> style="display: none;"<?php } ?>>
	<h4><?=Text::translate("Database Populated List Options")?></h4>
	
	<fieldset>
		<label for="options_field_table"><?=Text::translate("Table")?></label>
		<select id="options_field_table" name="pop-table" class="table_select">
			<option></option>
			<?php SQL::drawTableSelectOptions($options["pop-table"]); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label for="options_field_description"><?=Text::translate("Description Field")?></label>
		<div data-name="pop-description" class="pop-dependant pop-table">
			<?php if ($options["pop-table"]) { ?>
			<select id="options_field_description" name="pop-description"><?php SQL::drawColumnSelectOptions($options["pop-table"],$options["pop-description"]) ?></select>
			<?php } else { ?>
			<input id="options_field_description" type="text" disabled="disabled" value="<?=Text::translate('Please select "Table"', true)?>" />
			<?php } ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label for="options_field_sort"><?=Text::translate("Sort By")?></label>
		<div data-name="pop-sort" class="sort_by pop-dependant pop-table">
			<?php if ($options["pop-table"]) { ?>
			<select id="options_field_sort" name="pop-sort"><?php SQL::drawColumnSelectOptions($options["pop-table"],$options["pop-sort"],true) ?></select>
			<?php } else { ?>
			<input id="options_field_sort" type="text" disabled="disabled" value="<?=Text::translate('Please select "Table"', true)?>" />
			<?php } ?>
		</div>	
	</fieldset>

	<fieldset>
		<label for="options_field_parser"><?=Text::translate("List Parser Function")?></label>
		<input id="options_field_parser" type="text" name="parser" value="<?=htmlspecialchars($options["parser"])?>" />
		<p class="note"><?=Text::translate("Your function will receive an array of the available entries and should return a modified array.")?></p>
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
		title: "<?=Text::translate("Static List Options", true)?>",
		columns: ["<?=Text::translate("Value", true)?>","<?=Text::translate("Description", true)?>"],
		keys: [{ key: "value", type: "text" }, { key: "description", type: "text" }],
		existing: <?=json_encode($options["list"])?>
	});
</script>