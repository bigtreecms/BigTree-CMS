<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */
?>
<fieldset>
	<label for="options_field_table"><?=Text::translate("Table")?></label>
	<select id="options_field_table" name="table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($options["table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_title"><?=Text::translate("Title Field")?></label>
	<span data-name="title_column" class="pop-dependant table">
		<?php if ($options["table"]) { ?>
		<select id="options_field_title" name="title_column"><?php SQL::drawColumnSelectOptions($options["table"], $options["title_column"]) ?></select>
		<?php } else { ?>
		<input id="options_field_title" type="text" disabled="disabled" value="<?=Text::translate('Please select "Table"', true)?>" />
		<?php } ?>
	</span>
</fieldset>
<fieldset>
	<label for="options_field_sort"><?=Text::translate("Sort By")?></label>
	<span data-name="sort_by_column" class="sort_by pop-dependant table">
		<?php if ($options["table"]) { ?>
		<select id="options_field_sort" name="sort_by_column"><?php SQL::drawColumnSelectOptions($options["table"], $options["sort_by_column"],true) ?></select>
		<?php } else { ?>
		<input id="options_field_sort" type="text" disabled="disabled" value="<?=Text::translate('Please select "Table"', true)?>" />
		<?php } ?>
	</span>
</fieldset>
<fieldset>
	<label for="options_field_parser"><?=Text::translate("List Parser Function")?></label>
	<input id="options_field_parser" type="text" name="parser" value="<?=htmlspecialchars($options["parser"])?>" />
	<p class="note"><?=Text::translate("The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true).")?></p>
</fieldset>
<fieldset>
	<input id="options_field_add_all" type="checkbox" name="show_add_all"<?php if ($options["show_add_all"]) { ?> checked="checked"<?php } ?>>
	<label for="options_field_add_all" class="for_checkbox"><?=Text::translate("Enable Add All Button")?></label>
</fieldset>
<fieldset>
	<input id="options_field_reset" type="checkbox" name="show_reset"<?php if ($options["show_reset"]) { ?> checked="checked"<?php } ?>>
	<label for="options_field_reset" class="for_checkbox"><?=Text::translate("Enable Reset Button")?></label>
</fieldset>