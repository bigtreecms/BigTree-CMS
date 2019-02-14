<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
?>
<fieldset>
	<input id="settings_field_default_today" type="checkbox" name="default_today"<?php if (!empty($settings["default_today"])) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_default_today" class="for_checkbox"><?=Text::translate("Default to Today's Date")?></label>
</fieldset>