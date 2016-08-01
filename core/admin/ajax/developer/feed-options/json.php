<?php
	namespace BigTree;

	/**
	 * @global array $options
	 * @global string $table
	 */

	if (!$table) {
?>
<p><?=Text::translate("Please select a table first.")?></p>
<?php
	} else {
?>
<fieldset>
	<input id="options_field_condensed" type="checkbox" name="condensed"<?php if ($options["condensed"]) { ?> checked="checked"<?php } ?> />
	<label for="options_field_condensed" class="for_checkbox"><?=Text::translate("Condensed Output <small>(defaults to pretty print if unchecked)</small>")?></label>
</fieldset>
<fieldset>
	<label for="options_field_sort"><?=Text::translate("Order By")?></label>
	<select id="options_field_sort" name="sort">
		<?php SQL::drawColumnSelectOptions($table, $options["sort"], true); ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_limit"><?=Text::translate("Limit <small>(defaults to 15)</small>")?></label>
	<input id="options_field_limit" type="text" name="limit" value="<?=$options["limit"]?>" />
</fieldset>
<?php
	}
?>