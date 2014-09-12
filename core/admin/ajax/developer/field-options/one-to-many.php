<fieldset>
	<label>Table</label>
	<select name="table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($data["table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label>Title Field</label>
	<span data-name="title_column" class="pop-dependant table">
		<? if ($data["table"]) { ?>
		<select name="title_column"><? BigTree::getFieldSelectOptions($data["table"],$data["title_column"]) ?></select>
		<? } else { ?>
		<input type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
		<? } ?>
	</span>
</fieldset>
<fieldset>
	<label>Sort By</label>
	<span data-name="sort_by_column" class="sort_by pop-dependant table">
		<? if ($data["table"]) { ?>
		<select name="sort_by_column"><? BigTree::getFieldSelectOptions($data["table"],$data["sort_by_column"],true) ?></select>
		<? } else { ?>
		<input type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
		<? } ?>
	</span>
</fieldset>
<fieldset>
	<label>List Parser Function</label>
	<input type="text" name="parser" value="<?=htmlspecialchars($data["parser"])?>" />
	<p class="note">The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true).</p>
</fieldset>
<fieldset>
	<input type="checkbox" name="show_add_all"<? if ($data["show_add_all"]) { ?> checked="checked"<? } ?>>
	<label class="for_checkbox">Enable Add All Button</label>
</fieldset>
<fieldset>
	<input type="checkbox" name="show_reset"<? if ($data["show_reset"]) { ?> checked="checked"<? } ?>>
	<label class="for_checkbox">Enable Reset Button</label>
</fieldset>