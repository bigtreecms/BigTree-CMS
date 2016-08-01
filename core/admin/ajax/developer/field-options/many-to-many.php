<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */
?>
<fieldset>
	<label for="options_field_connecting"><?=Text::translate("Connecting Table")?></label>
	<select id="options_field_connecting" name="mtm-connecting-table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($options["mtm-connecting-table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label for="options_field_myid"><?=Text::translate("My ID")?></label>
	<div data-name="mtm-my-id" class="pop-dependant mtm-connecting-table">
		<?php if ($options["mtm-connecting-table"]) { ?>
		<select id="options_field_myid" name="mtm-my-id"><?php SQL::drawColumnSelectOptions($options["mtm-connecting-table"], $options["mtm-my-id"]) ?></select>
		<?php } else { ?>
		<input id="options_field_myid" type="text" disabled="disabled" value="<?=Text::translate('Please select "Connecting Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_otherid"><?=Text::translate("Other ID")?></label>
	<div data-name="mtm-other-id" class="pop-dependant mtm-connecting-table">
		<?php if ($options["mtm-connecting-table"]) { ?>
		<select id="options_field_otherid" name="mtm-other-id"><?php SQL::drawColumnSelectOptions($options["mtm-connecting-table"], $options["mtm-other-id"]) ?></select>
		<?php } else { ?>
		<input id="options_field_otherid" type="text" disabled="disabled" value="<?=Text::translate('Please select "Connecting Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_othertable"><?=Text::translate("Other Table")?></label>
	<select id="options_field_othertable" name="mtm-other-table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($options["mtm-other-table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label for="options_field_descriptor"><?=Text::translate("Other Descriptor")?></label>
	<div data-name="mtm-other-descriptor" class="pop-dependant mtm-other-table">
		<?php if ($options["mtm-other-table"]) { ?>
		<select id="options_field_descriptor" name="mtm-other-descriptor"><?php SQL::drawColumnSelectOptions($options["mtm-other-table"], $options["mtm-other-descriptor"]) ?></select>
		<?php } else { ?>
		<input id="options_field_descriptor" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"')?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_sort"><?=Text::translate("Sort By")?></label>
	<div data-name="mtm-sort" class="sort_by pop-dependant mtm-other-table">
		<?php if ($options["mtm-other-table"]) { ?>
		<select id="options_field_sort" name="mtm-sort"><?php SQL::drawColumnSelectOptions($options["mtm-other-table"], $options["mtm-sort"], true) ?></select>
		<?php } else { ?>
		<input id="options_field_sort" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_parser"><?=Text::translate("List Parser Function")?></label>
	<input id="options_field_parser" type="text" name="mtm-list-parser" value="<?=htmlspecialchars($options["mtm-list-parser"])?>" />
	<p class="note"><?=Text::translate("The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true).")?></p>
</fieldset>

<fieldset>
	<input id="options_field_addall" type="checkbox" name="show_add_all"<?php if ($options["show_add_all"]) { ?> checked="checked"<?php } ?>>
	<label for="options_field_addall" class="for_checkbox"><?=Text::translate("Enable Add All Button")?></label>
</fieldset>

<fieldset>
	<input id="options_field_reset" type="checkbox" name="show_reset"<?php if ($options["show_reset"]) { ?> checked="checked"<?php } ?>>
	<label for="options_field_reset" class="for_checkbox"><?=Text::translate("Enable Reset Button")?></label>
</fieldset>