<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 * @global string $table
	 */

	// Defaults
	$nesting_column = isset($settings["nesting_column"]) ? $settings["nesting_column"] : "";
?>
<fieldset>
	<label for="settings_field_nesting_column"><?=Text::translate('Nesting Column <small>(i.e. "parent")</small>')?></label>
	<select id="settings_field_nesting_column" name="nesting_column">
		<?php SQL::drawColumnSelectOptions($table, $nesting_column) ?>
	</select>
</fieldset>