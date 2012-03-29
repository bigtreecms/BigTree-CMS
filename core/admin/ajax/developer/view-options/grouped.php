<fieldset>
	<label>Group Field</label>
	<select name="group_field">
		<? BigTree::getFieldSelectOptions($table,$d["group_field"]) ?>
	</select>
</fieldset>

<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($d["draggable"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Field to Sort By Inside Groups <small>(if not draggable)</small></label>
	<div name="sort_field">
		<select name="sort_field">
			<? BigTree::getFieldSelectOptions($table,$d["sort_field"]) ?>
		</select>
	</div>
</fieldset>

<fieldset>
	<label>Sort Direction <small>(if not draggable)</small></label>
	<select name="sort_direction">
		<option>asc</option>
		<option<? if ($d["sort_direction"] == "desc") { ?> selected="selected"<? } ?>>desc</option>
	</select>
</fieldset>

<h4>Optional Parameters</h4>

<fieldset>
	<label>Other Table</label>
	<select name="other_table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($d["other_table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label>Field to Pull for Title</label>
	<div name="title_field">
		<? if ($d["other_table"]) { ?>
		<select name="title_field">
			<? BigTree::getFieldSelectOptions($d["other_table"],$d["title_field"]) ?>
		</select>
		<? } else { ?>
		&mdash;
		<? } ?>
	</div>
</fieldset>

<fieldset>
	<label>Field to Sort By</label>
	<div name="sort_field">
		<? if ($d["other_table"]) { ?>
		<select name="ot_sort_field">
			<? BigTree::getFieldSelectOptions($d["other_table"],$d["sort_field"]) ?>
		</select>
		<? } else { ?>
		&mdash;
		<? } ?>
	</div>
</fieldset>

<fieldset>
	<label>Sort Direction</label>
	<select name="ot_sort_direction">
		<option>asc</option>
		<option<? if ($d["sort_direction"] == "desc") { ?> selected="selected"<? } ?>>desc</option>
	</select>
</fieldset>