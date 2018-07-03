<fieldset>
	<label for="settings_field_connecting_table">Connecting Table</label>
	<select id="settings_field_connecting_table" name="mtm-connecting-table" class="table_select">
		<option></option>
		<?php BigTree::getTableSelectOptions($settings["mtm-connecting-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label for="settings_field_my_id">My ID</label>
	<div data-name="mtm-my-id" class="pop-dependant mtm-connecting-table">
		<?php if ($settings["mtm-connecting-table"]) { ?>
		<select id="settings_field_my_id" name="mtm-my-id"><?php BigTree::getFieldSelectOptions($settings["mtm-connecting-table"],$settings["mtm-my-id"]) ?></select>
		<?php } else { ?>
		<input id="settings_field_my_id" type="text" disabled="disabled" value="Please select &quot;Connecting Table&quot;" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label for="settings_field_other_id">Other ID</label>
	<div data-name="mtm-other-id" class="pop-dependant mtm-connecting-table">
		<?php if ($settings["mtm-connecting-table"]) { ?>
		<select id="settings_field_other_id" name="mtm-other-id"><?php BigTree::getFieldSelectOptions($settings["mtm-connecting-table"],$settings["mtm-other-id"]) ?></select>
		<?php } else { ?>
		<input id="settings_field_other_id" type="text" disabled="disabled" value="Please select &quot;Connecting Table&quot;" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label for="settings_field_other_table">Other Table</label>
	<select id="settings_field_other_table" name="mtm-other-table" class="table_select">
		<option></option>
		<?php BigTree::getTableSelectOptions($settings["mtm-other-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label for="settings_field_other_descriptor">Other Descriptor</label>
	<div id="settings_field_other_descriptor" data-name="mtm-other-descriptor" class="pop-dependant mtm-other-table">
		<?php if ($settings["mtm-other-table"]) { ?>
		<select name="mtm-other-descriptor"><?php BigTree::getFieldSelectOptions($settings["mtm-other-table"],$settings["mtm-other-descriptor"]) ?></select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="Please select &quot;Other Table&quot;" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label for="settings_field_sort_by">Sort By</label>
	<div data-name="mtm-sort" class="sort_by pop-dependant mtm-other-table">
		<?php if ($settings["mtm-other-table"]) { ?>
		<select id="settings_field_sort_by" name="mtm-sort"><?php BigTree::getFieldSelectOptions($settings["mtm-other-table"],$settings["mtm-sort"],true) ?></select>
		<?php } else { ?>
		<input id="settings_field_sort_by" type="text" disabled="disabled" value="Please select &quot;Other Table&quot;" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label for="settings_field_list_parser">List Parser Function</label>
	<input id="settings_field_list_parser" type="text" name="mtm-list-parser" value="<?=htmlspecialchars($settings["mtm-list-parser"])?>" />
	<p class="note">The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true).</p>
</fieldset>
<fieldset>
	<label for="settings_field_max">Maximum Entries <small>(defaults to unlimited)</small></label>
	<input id="settings_field_max" type="text" name="max" value="<?=$settings["max"]?>" autocomplete="off" />
</fieldset>
<fieldset id="settings_fieldset_add_all"<?php if ($settings["max"]) { ?> style="display: none;"<?php } ?>>
	<input id="settings_field_add_all" type="checkbox" name="show_add_all"<?php if ($data["show_add_all"]) { ?> checked="checked"<?php } ?>>
	<label for="settings_field_add_all" class="for_checkbox">Enable Add All Button <small>(will not show if Maximum Entries is set)</small></label>
</fieldset>
<fieldset>
	<input id="settings_field_reset" type="checkbox" name="show_reset"<?php if ($data["show_reset"]) { ?> checked="checked"<?php } ?>>
	<label for="settings_field_reset" class="for_checkbox">Enable Reset Button</label>
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