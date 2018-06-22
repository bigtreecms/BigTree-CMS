<?php
	$settings["min_width"] = isset($settings["min_width"]) ? intval($settings["min_width"]) : "";
	$settings["min_height"] = isset($settings["min_height"]) ? intval($settings["min_height"]) : "";
?>
<fieldset>
	<label for="settings_field_min_width">Minimum Width <small>(numeric value in pixels)</small></label>
	<input id="settings_field_min_width" type="text" name="min_width" value="<?=$settings["min_width"]?>" />
</fieldset>
<fieldset>
	<label for="settings_field_min_height">Minimum Height <small>(numeric value in pixels)</small></label>
	<input id="settings_field_min_height" type="text" name="min_height" value="<?=$settings["min_height"]?>" />
</fieldset>