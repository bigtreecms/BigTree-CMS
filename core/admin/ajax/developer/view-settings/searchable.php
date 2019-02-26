<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 * @global string $table
	 */

	// Defaults
	$sort = isset($settings["sort"]) ? $settings["sort"] : "id DESC";
	$per_page = isset($settings["per_page"]) ? $settings["per_page"] : 15;
?>
<fieldset>
	<label for="settings_field_sort"><?=Text::translate("Sort By")?></label>
	<?php if ($table) { ?>
	<select id="settings_field_sort" name="sort">
		<?php SQL::drawColumnSelectOptions($table, $sort, true) ?>
	</select>
	<?php } else { ?>
	<input id="settings_field_sort" name="sort" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>
<fieldset>
	<label for="settings_field_per_page"><?=Text::translate("Items Per Page")?></label>
	<input id="settings_field_per_page" type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>