<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["function"] = isset($options["function"]) ? $options["function"] : "";
	$options["process_function"] = isset($options["process_function"]) ? $options["process_function"] : "";
?>
<fieldset>
	<label for="options_function_field"><?=Text::translate('Drawing Call <small>(function name only, receives $key, $value, $title, $subtitle, $tabindex)</small>')?></label>
	<input id="options_function_field" type="text" name="function" value="<?=htmlspecialchars($options["function"])?>" />
</fieldset>
<fieldset>
	<label for="options_process_field"><?=Text::translate('Processing Call <small>(optional, function name only, receives: $value, $key)</small>')?></label>
	<input id="options_process_field" type="text" name="process_function" value="<?=htmlspecialchars($options["process_function"])?>" />
</fieldset>