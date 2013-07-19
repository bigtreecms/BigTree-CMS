<h4>Many To Many Options</h4>
<fieldset>
	<label>Connecting Table</label>
	<select name="mtm-connecting-table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($data["mtm-connecting-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label>My ID</label>
	<div data-name="mtm-my-id" class="pop-dependant mtm-connecting-table">
		<? if ($data["mtm-connecting-table"]) { ?>
		<select name="mtm-my-id"><? BigTree::getFieldSelectOptions($data["mtm-connecting-table"],$data["mtm-my-id"]) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>Other ID</label>
	<div data-name="mtm-other-id" class="pop-dependant mtm-connecting-table">
		<? if ($data["mtm-connecting-table"]) { ?>
		<select name="mtm-other-id"><? BigTree::getFieldSelectOptions($data["mtm-connecting-table"],$data["mtm-other-id"]) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>Other Table</label>
	<select name="mtm-other-table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($data["mtm-other-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label>Other Descriptor</label>
	<div data-name="mtm-other-descriptor" class="pop-dependant mtm-other-table">
		<? if ($data["mtm-other-table"]) { ?>
		<select name="mtm-other-descriptor"><? BigTree::getFieldSelectOptions($data["mtm-other-table"],$data["mtm-other-descriptor"]) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>Sort By</label>
	<div data-name="mtm-sort" class="sort_by pop-dependant mtm-other-table">
		<? if ($data["mtm-other-table"]) { ?>
		<select name="mtm-sort"><? BigTree::getFieldSelectOptions($data["mtm-other-table"],$data["mtm-sort"],true) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>List Parser Function</label>
	<input type="text" name="mtm-list-parser" value="<?=htmlspecialchars($data["mtm-list-parser"])?>" />
</fieldset>
<br />