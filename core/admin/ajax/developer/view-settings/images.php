<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 * @global string $table
	 */

	$draggable = isset($settings["draggable"]) ? $settings["draggable"] : "";
	$prefix = isset($settings["prefix"]) ? $settings["prefix"] : "";
	$image = isset($settings["image"]) ? $settings["image"] : "";
	$sort = isset($settings["sort"]) ? $settings["sort"] : "DESC";
?>
<fieldset>
	<input id="settings_field_draggable" type="checkbox" class="checkbox" name="draggable" <?php if ($draggable) { ?>checked="checked" <?php } ?>/>
	<label for="settings_field_draggable" class="for_checkbox"><?=Text::translate("Draggable")?></label>
</fieldset>

<fieldset>
	<label for="settings_field_prefix"><?=Text::translate("Image Prefix <small>(for using thumbnails, i.e. &ldquo;thumb_&rdquo;)</small>")?></label>
	<input id="settings_field_prefix" type="text" name="prefix" value="<?=htmlspecialchars($prefix)?>" />
</fieldset>

<fieldset>
	<label for="settings_field_image"><?=Text::translate("Image Field")?></label>
	<?php if ($table) { ?>
	<select id="settings_field_image" name="image">
		<?php SQL::drawColumnSelectOptions($table,$image) ?>
	</select>
	<?php } else { ?>
	<input id="settings_field_image" name="image" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>

<fieldset>
	<label for="settings_field_sort"><?=Text::translate("Sort Direction<small>(if not draggable)</small>")?></label>
	<select id="settings_field_sort" name="sort">
		<option value="DESC"><?=Text::translate("Newest First")?></option>
		<option value="ASC"<?php if ($sort == "ASC") { ?> selected="selected"<?php } ?>><?=Text::translate("Oldest First")?></option>
	</select>
</fieldset>