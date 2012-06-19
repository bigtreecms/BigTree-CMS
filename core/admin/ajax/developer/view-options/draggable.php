<?
	// Defaults
	$filter = isset($options["filter"]) ? $options["filter"] : "";
?>
<fieldset>
	<label>Filter Function <small>(function name only)</small></label>
	<input type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
</fieldset>