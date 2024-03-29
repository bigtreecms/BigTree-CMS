<?php if (empty($table)) { ?>
<p>Please select a table first.</p>
<?php } else { ?>
<fieldset>
	<label>Order By</label>
	<select name="sort">
		<?php BigTree::getFieldSelectOptions($table, $data["sort"] ?? "", true); ?>
	</select>
</fieldset>
<?php } ?>

<fieldset>
	<label>Limit <small>(defaults to 15)</small></label>
	<input type="text" name="limit" value="<?=$data["limit"] ?? ""?>" />
</fieldset>

<fieldset>
	<label>Parser Function <small>(accepts array of rows from the table, returns a filtered array)</small></label>
	<input type="text" name="parser" value="<?=BigTree::safeEncode($data["parser"] ?? "")?>" />
</fieldset>