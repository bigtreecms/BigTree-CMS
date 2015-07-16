<?php if (!$table) { ?>
<p>Please select a table first.</p>
<?php } else { ?>
<fieldset>
	<input type="checkbox" name="condensed"<?php if ($data["condensed"]) { ?> checked="checked"<?php } ?> />
	<label class="for_checkbox">Condensed Output <small>(defaults to pretty print if unchecked)</small></label>
</fieldset>
<fieldset>
	<label>Order By</label>
	<select name="sort">
		<?php BigTree::getFieldSelectOptions($table,$data["sort"],true); ?>
	</select>
</fieldset>
<fieldset>
	<label>Limit <small>(defaults to 15)</small></label>
	<input type="text" name="limit" value="<?=$data["limit"]?>" />
</fieldset>
<?php } ?>