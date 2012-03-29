<h4>Many To Many Options</h4>
<fieldset>
	<label>Connecting Table</label>
	<select name="mtm-connecting-table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($d["mtm-connecting-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label>My ID</label>
	<div name="mtm-my-id" class="pop-dependant mtm-connecting-table">
		<? if ($d["mtm-connecting-table"]) { ?>
		<select name="mtm-my-id"><? BigTree::getFieldSelectOptions($d["mtm-connecting-table"],$d["mtm-my-id"]) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>Other ID</label>
	<div name="mtm-other-id" class="pop-dependant mtm-connecting-table">
		<? if ($d["mtm-connecting-table"]) { ?>
		<select name="mtm-other-id"><? BigTree::getFieldSelectOptions($d["mtm-connecting-table"],$d["mtm-other-id"]) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>Other Table</label>
	<select name="mtm-other-table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($d["mtm-other-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label>Other Descriptor</label>
	<div name="mtm-other-descriptor" class="pop-dependant mtm-other-table">
		<? if ($d["mtm-other-table"]) { ?>
		<select name="mtm-other-descriptor"><? BigTree::getFieldSelectOptions($d["mtm-other-table"],$d["mtm-other-descriptor"]) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>Sort By</label>
	<div name="mtm-sort" class="sort_by pop-dependant mtm-other-table">
		<? if ($d["mtm-other-table"]) { ?>
		<select name="mtm-sort"><? BigTree::getFieldSelectOptions($d["mtm-other-table"],$d["mtm-sort"],true) ?></select>
		<? } else { ?>
		<small>-- Please choose a table. --</small>
		<? } ?>
	</div>
</fieldset>
<fieldset>
	<label>List Parser Function</label>
	<input name="mtm-list-parser" value="<?=htmlspecialchars($d["mtm-list-parser"])?>" />
</fieldset>