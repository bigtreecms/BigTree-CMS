<?
	$draggable = isset($options["draggable"]) ? $options["draggable"] : "";
	$prefix = isset($options["prefix"]) ? $options["prefix"] : "";
	$image = isset($options["image"]) ? $options["image"] : "";
	$sort = isset($options["sort"]) ? $options["sort"] : "DESC";
?>
<fieldset>
	<input type="checkbox" class="checkbox" name="draggable" <? if ($draggable) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Draggable</label>
</fieldset>

<fieldset>
	<label>Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small></label>
	<input type="text" name="prefix" value="<?=htmlspecialchars($prefix)?>" />
</fieldset>

<fieldset>
	<label>Image Field</label>
	<? if ($table) { ?>
	<select name="image">
		<? BigTree::getFieldSelectOptions($table,$image) ?>
	</select>
	<? } else { ?>
	<input name="image" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<? } ?>
</fieldset>

<fieldset>
	<label>Sort Direction<small>(if not draggable)</small></label>
	<select name="sort">
		<option value="DESC">Newest First</option>
		<option value="ASC"<? if ($sort == "ASC") { ?> selected="selected"<? } ?>>Oldest First</option>
	</select>
</fieldset>