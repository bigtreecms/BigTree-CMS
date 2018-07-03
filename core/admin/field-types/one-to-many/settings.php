<fieldset>
	<label for="settings_field_table">Table</label>
	<select id="settings_field_table" name="table" class="table_select">
		<option></option>
		<?php BigTree::getTableSelectOptions($data["table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label for="settings_field_title">Title Field</label>
	<span data-name="title_column" class="pop-dependant table">
		<?php if ($data["table"]) { ?>
		<select id="settings_field_title" name="title_column"><?php BigTree::getFieldSelectOptions($data["table"],$data["title_column"]) ?></select>
		<?php } else { ?>
		<input id="settings_field_title" type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
		<?php } ?>
	</span>
</fieldset>
<fieldset>
	<label for="settings_field_sort_by">Sort By</label>
	<span data-name="sort_by_column" class="sort_by pop-dependant table">
		<?php if ($data["table"]) { ?>
		<select id="settings_field_sort_by" name="sort_by_column"><?php BigTree::getFieldSelectOptions($data["table"],$data["sort_by_column"],true) ?></select>
		<?php } else { ?>
		<input id="settings_field_sort_by" type="text" disabled="disabled" value="Please select &quot;Table&quot;" />
		<?php } ?>
	</span>
</fieldset>
<fieldset>
	<label for="settings_field_list_parser">List Parser Function</label>
	<input id="settings_field_list_parser" type="text" name="parser" value="<?=htmlspecialchars($data["parser"])?>" />
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