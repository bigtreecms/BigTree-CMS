<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["source"] = isset($options["source"]) ? $options["source"] : "";
	$options["not_unique"] = isset($options["not_unique"]) ? $options["not_unique"] : "";
	$options["keep_original"] = isset($options["keep_original"]) ? $options["keep_original"] : "";
?>
<fieldset>
	<label for="options_field_source"><?=Text::translate("Source Field <small>(the table column to use for route generation)</small>")?></label>
	<select id="options_field_source" name="source">
		<?php SQL::drawColumnSelectOptions($_POST["table"], $options["source"]) ?>
	</select>
</fieldset>

<fieldset>
	<input id="options_field_unique" type="checkbox" name="not_unique" <?php if ($options["not_unique"]) { ?>checked="checked" <?php } ?>/>
	<label for="options_field_unique" class="for_checkbox"><?=Text::translate("Disregard Uniqueness<small>(if this box is checked duplicate routes can exist)</small>")?></label>
</fieldset>

<fieldset>
	<input id="options_field_original" type="checkbox" name="keep_original" <?php if ($options["keep_original"]) { ?>checked="checked" <?php } ?>/>
	<label for="options_field_original" class="for_checkbox"><?=Text::translate("Keep Original Route<small>(check to keep the first generated route)</small>")?></label>
</fieldset>