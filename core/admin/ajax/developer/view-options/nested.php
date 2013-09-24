<?
	// Defaults
	$filter = isset($options["filter"]) ? $options["filter"] : "";
	$nesting_column = isset($options["nesting_column"]) ? $options["nesting_column"] : "";
?>
<fieldset>
	<label>Nesting Column <small>(i.e. "parent")</small></label>
	<select name="nesting_column">
		<? BigTree::getFieldSelectOptions($table,$nesting_column,true) ?>
	</select>
</fieldset>
<fieldset>
	<label>Filter Function <small>(function name only)</small></label>
	<input type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
</fieldset>