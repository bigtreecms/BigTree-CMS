<?php
	namespace BigTree;

	// Prevent Notices
	$data["list_type"] = isset($data["list_type"]) ? $data["list_type"] : "";
	$data["allow-empty"] = isset($data["allow-empty"]) ? $data["allow-empty"] : "";
	$data["pop-table"] = isset($data["pop-table"]) ? $data["pop-table"] : "";
	$data["pop-description"] = isset($data["pop-description"]) ? $data["pop-description"] : "";
	$data["pop-sort"] = isset($data["pop-sort"]) ? $data["pop-sort"] : "";
	$data["list"] = isset($data["list"]) ? $data["list"] : array();
	
	$types = array(
		"static" => Text::translate("Static"),
		"db" => Text::translate("Database Populated"),
		"state" => Text::translate("State List"),
		"country" => Text::translate("Country List")
	);
?>
<fieldset>
	<label><?=Text::translate("List Type")?></label>
	<select name="list_type" id="field_list_types">
		<?php foreach ($types as $val => $desc) { ?>
		<option value="<?=$val?>"<?php if ($val == $data["list_type"]) { ?> selected="selected"<?php } ?>><?=$desc?></option>
		<?php } ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Allow Empty <small>(first option is blank)</small>")?></label>
	<select name="allow-empty">
		<option value="Yes"><?=Text::translate("Yes")?></option>
		<option value="No"<?php if ($data["allow-empty"] == "No") { ?> selected="selected"<?php } ?>><?=Text::translate("No")?></option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<?php if ($data["list_type"] && $data["list_type"] != "static") { ?> style="display: none;"<?php } ?>>
</div>

<div class="list_type_options" id="db_list_options"<?php if ($data["list_type"] != "db") { ?> style="display: none;"<?php } ?>>
	<h4><?=Text::translate("Database Populated List Options")?></h4>
	<fieldset>
		<label><?=Text::translate("Table")?></label>
		<select name="pop-table" class="table_select">
			<option></option>
			<?php BigTree::getTableSelectOptions($data["pop-table"]); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label><?=Text::translate("Description Field")?></label>
		<div data-name="pop-description" class="pop-dependant pop-table">
			<?php if ($data["pop-table"]) { ?>
			<select name="pop-description"><?php BigTree::getFieldSelectOptions($data["pop-table"],$data["pop-description"]) ?></select>
			<?php } else { ?>
			<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Table"', true)?>" />
			<?php } ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label><?=Text::translate("Sort By")?></label>
		<div data-name="pop-sort" class="sort_by pop-dependant pop-table">
			<?php if ($data["pop-table"]) { ?>
			<select name="pop-sort"><?php BigTree::getFieldSelectOptions($data["pop-table"],$data["pop-sort"],true) ?></select>
			<?php } else { ?>
			<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Table"', true)?>" />
			<?php } ?>
		</div>	
	</fieldset>

	<fieldset>
		<label><?=Text::translate("List Parser Function")?></label>
		<input type="text" name="parser" value="<?=htmlspecialchars($data["parser"])?>" />
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
		existing: <?=json_encode($data["list"])?>
	});
</script>