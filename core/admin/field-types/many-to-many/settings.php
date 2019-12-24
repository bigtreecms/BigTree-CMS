<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
	$text_please_select_connecting_table = Text::translate('Please select "Connecting Table"', true);
	$text_please_select_other_table = Text::translate('Please select "Connecting Table"', true);
?>
<fieldset>
	<label for="settings_field_connecting_table"><?=Text::translate("Connecting Table")?></label>
	<select id="settings_field_connecting_table" name="mtm-connecting-table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($settings["mtm-connecting-table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label for="settings_field_my_id"><?=Text::translate("My ID")?></label>
	<div data-name="mtm-my-id" class="pop-dependant mtm-connecting-table">
		<?php if ($settings["mtm-connecting-table"]) { ?>
		<select id="settings_field_my_id" name="mtm-my-id"><?php SQL::drawColumnSelectOptions($settings["mtm-connecting-table"], $settings["mtm-my-id"]) ?></select>
		<?php } else { ?>
		<input id="settings_field_my_id" type="text" disabled="disabled" value="<?=$text_please_select_connecting_table?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="settings_field_other_id"><?=Text::translate("Other ID")?></label>
	<div data-name="mtm-other-id" class="pop-dependant mtm-connecting-table">
		<?php if ($settings["mtm-connecting-table"]) { ?>
		<select id="settings_field_other_id" name="mtm-other-id"><?php SQL::drawColumnSelectOptions($settings["mtm-connecting-table"], $settings["mtm-other-id"]) ?></select>
		<?php } else { ?>
		<input id="settings_field_other_id" type="text" disabled="disabled" value="<?=$text_please_select_connecting_table?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="settings_field_other_table"><?=Text::translate("Other Table")?></label>
	<select id="settings_field_other_table" name="mtm-other-table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($settings["mtm-other-table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label for="settings_field_other_descriptor"><?=Text::translate("Other Descriptor")?></label>
	<div id="settings_field_other_descriptor" data-name="mtm-other-descriptor" class="pop-dependant mtm-other-table">
		<?php if ($settings["mtm-other-table"]) { ?>
		<select name="mtm-other-descriptor"><?php SQL::drawColumnSelectOptions($settings["mtm-other-table"], $settings["mtm-other-descriptor"]) ?></select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=$text_please_select_other_table?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="settings_field_sort_by"><?=Text::translate("Sort By")?>/label>
	<div data-name="mtm-sort" class="sort_by pop-dependant mtm-other-table">
		<?php if ($settings["mtm-other-table"]) { ?>
		<select id="settings_field_sort_by" name="mtm-sort"><?php SQL::drawColumnSelectOptions($settings["mtm-other-table"], $settings["mtm-sort"], true) ?></select>
		<?php } else { ?>
		<input id="settings_field_sort_by" type="text" disabled="disabled" value="<?=$text_please_select_other_table?>" />
		<?php } ?>
	</div>
</fieldset>

<fieldset>
	<label for="settings_field_list_parser"><?=Text::translate("List Parser Function")?></label>
	<input id="settings_field_list_parser" type="text" name="mtm-list-parser" value="<?=htmlspecialchars($settings["mtm-list-parser"])?>" />
	<p class="note"><?=Text::translate("The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true).")?></p>
</fieldset>

<fieldset>
	<label for="settings_field_min"><?=Text::translate("Minimum Entries")?></label>
	<input id="settings_field_min" type="text" name="min" value="<?=$settings["min"]?>" autocomplete="off" />
</fieldset>

<fieldset>
	<label for="settings_field_max"><?=Text::translate("Maximum Entries <small>(defaults to unlimited)</small>")?></label>
	<input id="settings_field_max" type="text" name="max" value="<?=$settings["max"]?>" autocomplete="off" />
</fieldset>

<fieldset id="settings_fieldset_add_all"<?php if ($settings["max"]) { ?> style="display: none;"<?php } ?>>
	<input id="settings_field_add_all" type="checkbox" name="show_add_all"<?php if ($settings["show_add_all"]) { ?> checked="checked"<?php } ?>>
	<label for="settings_field_add_all" class="for_checkbox"><?=Text::translate("Enable Add All Button <small>(will not show if Maximum Entries is set)</small>")?></label>
</fieldset>

<fieldset>
	<input id="settings_field_reset" type="checkbox" name="show_reset"<?php if ($settings["show_reset"]) { ?> checked="checked"<?php } ?>>
	<label for="settings_field_reset" class="for_checkbox"><?=Text::translate("Enable Reset Button")?></label>
</fieldset>

<script>
	$("#settings_field_max").keyup(function() {
		var max = parseInt($(this).val());

		if (max > 0) {
			$("#settings_fieldset_add_all").hide();
		} else {
			$("#settings_fieldset_add_all").show();
		}
	});
</script>