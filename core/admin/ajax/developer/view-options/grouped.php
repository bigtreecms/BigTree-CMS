<fieldset>
	<label>Group Field</label>
	<select name="group_field">
		<? BigTree::getFieldSelectOptions($table,$data["group_field"]) ?>
	</select>
</fieldset>

<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($data["draggable"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Field to Sort By Inside Groups <small>(if not draggable)</small></label>
	<div name="sort_field">
		<select name="sort_field">
			<? BigTree::getFieldSelectOptions($table,$data["sort_field"]) ?>
		</select>
	</div>
</fieldset>

<fieldset>
	<label>Sort Direction <small>(if not draggable)</small></label>
	<select name="sort_direction">
		<option>asc</option>
		<option<? if ($data["sort_direction"] == "desc") { ?> selected="selected"<? } ?>>desc</option>
	</select>
</fieldset>

<h4>Optional Parameters</h4>

<fieldset>
	<label>Other Table</label>
	<select name="other_table" class="table_select">
		<option></option>
		<? BigTree::getTableSelectOptions($data["other_table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label>Field to Pull for Title</label>
	<div data-name="title_field">
		<? if ($data["other_table"]) { ?>
		<select name="title_field">
			<? BigTree::getFieldSelectOptions($data["other_table"],$data["title_field"]) ?>
		</select>
		<? } else { ?>
		&mdash;
		<? } ?>
	</div>
</fieldset>

<fieldset>
	<label>Field to Sort By</label>
	<div data-name="sort_field">
		<? if ($data["other_table"]) { ?>
		<select name="ot_sort_field">
			<? BigTree::getFieldSelectOptions($data["other_table"],$data["sort_field"]) ?>
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
		<option<? if ($data["sort_direction"] == "desc") { ?> selected="selected"<? } ?>>desc</option>
	</select>
</fieldset>