<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["custom_value"] = isset($options["custom_value"]) ? $options["custom_value"] : "";
?>
<fieldset>
	<label for="options_field_value"><?=Text::translate('Value <small>(defaults to "on")</small>')?></label>
	<input id="options_field_value" type="text" name="custom_value" value="<?=htmlspecialchars($options["custom_value"])?>" />
</fieldset>