<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($data["draggable"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Image Directory <small>(relative to site root, i.e. &ldquo;images/features/&rdquo;)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($data["directory"])?>" />
</fieldset>

<fieldset>
	<label>Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo)</small></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($data["prefix"])?>" />
</fieldset>

<fieldset>
	<label>Image Field</label>
	<select name="image">
		<? BigTree::getFieldSelectOptions($table,$data["image"]) ?>
	</select>
</fieldset>

<fieldset>
	<label>Group Field</label>
	<select name="group_field">
		<? BigTree::getFieldSelectOptions($table,$data["group_field"]) ?>
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
	<div name="title_field">
		<? if ($data["title_field"]) { ?>
		<select name="title_field">
			<? BigTree::getFieldSelectOptions($data["other_table"],$data["title_field"]) ?>
		</select>
		<? } else { ?>
		&mdash;
		<? } ?>
	</div>
</fieldset>

<fieldset>
	<label>Group Name Parser <small>($item is the group data, set $value to the new name)</small></label>
	<textarea name="group_parser"><?=htmlspecialchars($data["group_parser"])?></textarea>
</fieldset>