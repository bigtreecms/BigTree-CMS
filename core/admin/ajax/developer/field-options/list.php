<?
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
		<option value="<?=$val?>"<? if ($val == $d["list_type"]) { ?> selected="selected"<? } ?>><?=$desc?></option>
		<? } ?>
	</select>
</fieldset>
<fieldset>
	<label>Allow Empty <small>(first option is blank)</small></label>
	<select name="allow-empty">
		<option value="Yes">Yes</option>
		<option value="No"<? if ($d["allow-empty"] == "No") { ?> selected="selected"<? } ?>>No</option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<? if ($d["list_type"] && $d["list_type"] != "static") { ?> style="display: none;"<? } ?>></div>

<div class="list_type_options" id="db_list_options"<? if ($d["list_type"] != "db") { ?> style="display: none;"<? } ?>>
	<h4>Database Populated List Options</h4>
	<fieldset>
		<label>Table</label>
		<select name="pop-table" class="table_select">
			<option></option>
			<? BigTree::getTableSelectOptions($d["pop-table"]); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label>Description Field</label>
		<div name="pop-description" class="pop-dependant pop-table">
			<? if ($d["pop-table"]) { ?>
			<select name="pop-description"><? BigTree::getFieldSelectOptions($d["pop-table"],$d["pop-description"]) ?></select>
			<? } else { ?>
			<small>-- Please select a table --</small>
			<? } ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label>Sort By</label>
		<div name="pop-sort" class="sort_by pop-dependant pop-table">
			<? if ($d["pop-table"]) { ?>
			<select name="pop-sort"><? BigTree::getFieldSelectOptions($d["pop-table"],$d["pop-sort"],true) ?></select>
			<? } else { ?>
			<small>-- Please select a table --</small>
			<? } ?>
		</div>	
	</fieldset>
</div>

<script type="text/javascript">	
	new BigTreeListMaker("#static_list_options","list","Static List Options",["Value","Description"],[{ key: "value", type: "text" },{ key: "description", type: "text" }],<?=json_encode($d["list"])?>);
</script>