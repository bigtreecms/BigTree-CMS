<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($data["draggable"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Image Directory <small>(relative to site root, i.e. &ldquo;images/features/&rdquo;)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($data["directory"])?>" />
</fieldset>

<fieldset>
	<label>Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($data["prefix"])?>" />
</fieldset>

<fieldset>
	<label>Image Field</label>
	<select name="image">
		<? BigTree::getFieldSelectOptions($table,$data["image"]) ?>
	</select>
</fieldset>