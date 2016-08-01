<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["default_now"] = isset($options["default_now"]) ? $options["default_now"] : "";
?>
<fieldset>
	<input id="options_field_now" type="checkbox" name="default_now"<?php if ($options["default_now"]) { ?> checked="checked"<?php } ?>/>
	<label for="options_field_now" class="for_checkbox"><?=Text::translate("Default to Today's Date &amp; Time")?></label>
</fieldset>