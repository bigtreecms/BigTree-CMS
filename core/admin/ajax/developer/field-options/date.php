<?php
	namespace BigTree;

	// Stop notices
	$data["default_today"] = isset($data["default_today"]) ? $data["default_today"] : "";
?>
<fieldset>
	<input type="checkbox" name="default_today"<?php if ($data["default_today"]) { ?> checked="checked"<?php } ?>/>
	<label class="for_checkbox"><?=Text::translate("Default to Today's Date")?></label>
</fieldset>