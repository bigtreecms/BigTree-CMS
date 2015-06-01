<?php
	// Stop notices
	$data["default_now"] = isset($data["default_now"]) ? $data["default_now"] : "";
?>
<fieldset>
	<input type="checkbox" name="default_now"<?php if ($data["default_now"]) { ?> checked="checked"<?php } ?>/>
	<label class="for_checkbox">Default to Today's Date &amp; Time</label>
</fieldset>