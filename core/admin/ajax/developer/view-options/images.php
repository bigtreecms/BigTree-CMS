<?
	// DAG GONE NOTICES!
	$data["draggable"] = isset($data["draggable"]) ? $data["draggable"] : "";
	$data["prefix"] = isset($data["prefix"]) ? $data["prefix"] : "";
	$data["image"] = isset($data["image"]) ? $data["image"] : "";
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($data["draggable"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
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