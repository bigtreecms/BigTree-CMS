<?
	// Prevent Notices
	$data["list_type"] = isset($data["list_type"]) ? $data["list_type"] : "";
	$data["allow-empty"] = isset($data["allow-empty"]) ? $data["allow-empty"] : "";
	$data["pop-table"] = isset($data["pop-table"]) ? $data["pop-table"] : "";
	$data["pop-description"] = isset($data["pop-description"]) ? $data["pop-description"] : "";
	$data["pop-sort"] = isset($data["pop-sort"]) ? $data["pop-sort"] : "";
	$data["list"] = isset($data["list"]) ? $data["list"] : array();
	
	$types = array(
		"static" => "Static",
		"db" => "Database Populated",
		"state" => "State List",
		"country" => "Country List"
	);
?>
<fieldset>
	<label>List Type</label>
	<select name="list_type" id="field_list_types">
		<? foreach ($types as $val => $desc) { ?>
		<option value="<?=$val?>"<? if ($val == $data["list_type"]) { ?> selected="selected"<? } ?>><?=$desc?></option>
		<? } ?>
	</select>
</fieldset>
<fieldset>
	<label>Allow Empty <small>(first option is blank)</small></label>
	<select name="allow-empty">
		<option value="Yes">Yes</option>
		<option value="No"<? if ($data["allow-empty"] == "No") { ?> selected="selected"<? } ?>>No</option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<? if ($data["list_type"] && $data["list_type"] != "static") { ?> style="display: none;"<? } ?>></div>

<div class="list_type_options" id="db_list_options"<? if ($data["list_type"] != "db") { ?> style="display: none;"<? } ?>>
	<h4>Database Populated List Options</h4>
	<fieldset>
		<label>Table</label>
		<select name="pop-table" class="table_select">
			<option></option>
			<? BigTree::getTableSelectOptions($data["pop-table"]); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label>Description Field</label>
		<div data-name="pop-description" class="pop-dependant pop-table">
			<? if ($data["pop-table"]) { ?>
			<select name="pop-description"><? BigTree::getFieldSelectOptions($data["pop-table"],$data["pop-description"]) ?></select>
			<? } else { ?>
			<input type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
			<? } ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label>Sort By</label>
		<div data-name="pop-sort" class="sort_by pop-dependant pop-table">
			<? if ($data["pop-table"]) { ?>
			<select name="pop-sort"><? BigTree::getFieldSelectOptions($data["pop-table"],$data["pop-sort"],true) ?></select>
			<? } else { ?>
			<input type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
			<? } ?>
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
	new BigTreeListMaker("#static_list_options","list","Static List Options",["Value","Description"],[{ key: "value", type: "text" },{ key: "description", type: "text" }],<?=json_encode($data["list"])?>);
</script>