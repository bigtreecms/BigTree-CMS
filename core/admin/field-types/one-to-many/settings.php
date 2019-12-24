<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
	$text_table = Text::translate('Please select "Table"', true);
?>
<fieldset>
	<label for="settings_field_table"><?=Text::translate("Table")?></label>
	<select id="settings_field_table" name="table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($settings["table"]) ?>
	</select>
</fieldset>

<fieldset>
	<label for="settings_field_title"><?=Text::translate("Title Field")?></label>
	<span data-name="title_column" class="pop-dependant table">
		<?php if ($settings["table"]) { ?>
		<select id="settings_field_title" name="title_column"><?php SQL::drawColumnSelectOptions($settings["table"], $settings["title_column"]) ?></select>
		<?php } else { ?>
		<input id="settings_field_title" type="text" disabled="disabled" value="<?=$text_table?>" />
		<?php } ?>
	</span>
</fieldset>

<fieldset>
	<label for="settings_field_sort_by"><?=Text::translate("Sort By")?></label>
	<span data-name="sort_by_column" class="sort_by pop-dependant table">
		<?php if ($settings["table"]) { ?>
		<select id="settings_field_sort_by" name="sort_by_column"><?php SQL::drawColumnSelectOptions($settings["table"], $settings["sort_by_column"], true) ?></select>
		<?php } else { ?>
		<input id="settings_field_sort_by" type="text" disabled="disabled" value="<?=$text_table?>" />
		<?php } ?>
	</span>
</fieldset>

<fieldset>
	<label for="settings_field_list_parser"><?=Text::translate("List Parser Function")?></label>
	<input id="settings_field_list_parser" type="text" name="parser" value="<?=Text::htmlEncode($settings["parser"])?>" />
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