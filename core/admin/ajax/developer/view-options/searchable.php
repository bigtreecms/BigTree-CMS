<?
	if (!empty($d)) {
		BigTree::globalizeArray($d);
	}

	if (!$sort_column) {
		$sort_column = "id";
	}
	if (!$per_page) {
		$per_page = 15;
	}
?>
<fieldset>
	<label>Sort By</label>
	<select name="sort_column" style="float: left; margin: 0 5px 0 0;">
		<? BigTree::getFieldSelectOptions($table,$sort_column) ?>
	</select> <select name="sort_direction"><option value="ASC">ASC</option><option<? if ($sort_direction == "DESC") { ?> selected="selected"<? } ?> value="DESC">DESC</option></select>
</fieldset>
<fieldset>
	<label>Items Per Page</label>
	<input type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>
<fieldset>
	<label>Filter Function <small>(function name only)</small></label>
	<input type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
</fieldset>