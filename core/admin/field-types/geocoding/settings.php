<?php
	// Stop notices
	$settings["fields"] = isset($settings["fields"]) ? $settings["fields"] : "";
?>
<fieldset class="last">
	<label>Fields To Pull Address From <small>(comma separated)</small></label>
	<input type="text" name="fields" value="<?=$settings["fields"]?>" />
</fieldset>