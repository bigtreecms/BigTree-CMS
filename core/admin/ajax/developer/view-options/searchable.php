<?
	if (!empty($d)) {
		BigTree::globalizeArray($d);
	}

	if (!$sort) {
		$sort = "id DESC";
	}
	if (!$per_page) {
		$per_page = 15;
	}
?>
<fieldset>
	<label>Sort By</label>
	<select name="sort" style="float: left; margin: 0 5px 0 0;">
		<? BigTree::getFieldSelectOptions($table,$sort,true) ?>
	</select>
</fieldset>
<fieldset>
	<label>Items Per Page</label>
	<input type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>
<fieldset>
	<label>Filter Function <small>(function name only)</small></label>
	<input type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
</fieldset>