<? if (!$table) { ?>
<p>Please select a table first.</p>
<? } else { ?>
<fieldset>
	<label>Order By</label>
	<select name="sort">
		<? BigTree::getFieldSelectOptions($table,$d["sort"],true); ?>
	</select>
</fieldset>
<fieldset>
	<label>Limit <small>(defaults to 15)</small></label>
	<input type="text" name="limit" value="<?=$d["limit"]?>" />
</fieldset>
<? } ?>