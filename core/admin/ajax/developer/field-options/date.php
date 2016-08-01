<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["default_today"] = isset($options["default_today"]) ? $options["default_today"] : "";
?>
<fieldset>
	<input id="options_date_field" type="checkbox" name="default_today"<?php if ($options["default_today"]) { ?> checked="checked"<?php } ?>/>
	<label for="options_date_field" class="for_checkbox"><?=Text::translate("Default to Today's Date")?></label>
</fieldset>