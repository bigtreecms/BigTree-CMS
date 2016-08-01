<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 * @global string $table
	 */

	// Defaults
	$nesting_column = isset($options["nesting_column"]) ? $options["nesting_column"] : "";
?>
<fieldset>
	<label for="options_field_nesting_column"><?=Text::translate('Nesting Column <small>(i.e. "parent")</small>')?></label>
	<select id="options_field_nesting_column" name="nesting_column">
		<?php SQL::drawColumnSelectOptions($table, $nesting_column) ?>
	</select>
</fieldset>