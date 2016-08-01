<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 * @global string $table
	 */

	// Defaults
	$sort = isset($options["sort"]) ? $options["sort"] : "id DESC";
	$per_page = isset($options["per_page"]) ? $options["per_page"] : 15;
?>
<fieldset>
	<label for="options_field_sort"><?=Text::translate("Sort By")?></label>
	<?php if ($table) { ?>
	<select id="options_field_sort" name="sort">
		<?php SQL::drawColumnSelectOptions($table, $sort, true) ?>
	</select>
	<?php } else { ?>
	<input id="options_field_sort" name="sort" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>
<fieldset>
	<label for="options_field_per_page"><?=Text::translate("Items Per Page")?></label>
	<input id="options_field_per_page" type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>