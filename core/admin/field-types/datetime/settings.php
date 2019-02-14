<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
?>
<fieldset>
	<input id="settings_field_default_now" type="checkbox" name="default_now"<?php if (!empty($settings["default_now"])) { ?> checked="checked"<?php } ?>/>
	<label for="settings_field_default_now" class="for_checkbox"><?=Text::translate("Default to Today's Date &amp; Time")?></label>
</fieldset>