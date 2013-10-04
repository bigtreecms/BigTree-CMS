<?
	// Defaults
	$sort = isset($options["sort"]) ? $options["sort"] : "id DESC";
	$per_page = isset($options["per_page"]) ? $options["per_page"] : 15;
	$filter = isset($options["filter"]) ? $options["filter"] : "";
?>
<fieldset>
	<label>Sort By</label>
	<? if ($table) { ?>
	<select name="sort">
		<? BigTree::getFieldSelectOptions($table,$sort,true) ?>
	</select>
	<? } else { ?>
	<input name="sort" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<? } ?>
</fieldset>
<fieldset>
	<label>Items Per Page</label>
	<input type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>
<fieldset>
	<label>Filter Function <small>(function name only)</small></label>
	<input type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
</fieldset>