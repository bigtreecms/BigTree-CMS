<?php
	namespace BigTree;
?>
<fieldset>
	<label><?=Text::translate("Connecting Table")?></label>
	<select name="mtm-connecting-table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($data["mtm-connecting-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("My ID")?></label>
	<div data-name="mtm-my-id" class="pop-dependant mtm-connecting-table">
		<?php if ($data["mtm-connecting-table"]) { ?>
		<select name="mtm-my-id"><?php SQL::drawColumnSelectOptions($data["mtm-connecting-table"],$data["mtm-my-id"]) ?></select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Connecting Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label><?=Text::translate("Other ID")?></label>
	<div data-name="mtm-other-id" class="pop-dependant mtm-connecting-table">
		<?php if ($data["mtm-connecting-table"]) { ?>
		<select name="mtm-other-id"><?php SQL::drawColumnSelectOptions($data["mtm-connecting-table"],$data["mtm-other-id"]) ?></select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Connecting Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label><?=Text::translate("Other Table")?></label>
	<select name="mtm-other-table" class="table_select">
		<option></option>
		<?php SQL::drawTableSelectOptions($data["mtm-other-table"]) ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Other Descriptor")?></label>
	<div data-name="mtm-other-descriptor" class="pop-dependant mtm-other-table">
		<?php if ($data["mtm-other-table"]) { ?>
		<select name="mtm-other-descriptor"><?php SQL::drawColumnSelectOptions($data["mtm-other-table"],$data["mtm-other-descriptor"]) ?></select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"')?>" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label><?=Text::translate("Sort By")?></label>
	<div data-name="mtm-sort" class="sort_by pop-dependant mtm-other-table">
		<?php if ($data["mtm-other-table"]) { ?>
		<select name="mtm-sort"><?php SQL::drawColumnSelectOptions($data["mtm-other-table"],$data["mtm-sort"],true) ?></select>
		<?php } else { ?>
		<input type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
		<?php } ?>
	</div>
</fieldset>
<fieldset>
	<label><?=Text::translate("List Parser Function")?></label>
	<input type="text" name="mtm-list-parser" value="<?=htmlspecialchars($data["mtm-list-parser"])?>" />
	<p class="note"><?=Text::translate("The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true).")?></p>
</fieldset>
<fieldset>
	<input type="checkbox" name="show_add_all"<?php if ($data["show_add_all"]) { ?> checked="checked"<?php } ?>>
	<label class="for_checkbox"><?=Text::translate("Enable Add All Button")?></label>
</fieldset>
<fieldset>
	<input type="checkbox" name="show_reset"<?php if ($data["show_reset"]) { ?> checked="checked"<?php } ?>>
	<label class="for_checkbox"><?=Text::translate("Enable Reset Button")?></label>
</fieldset>