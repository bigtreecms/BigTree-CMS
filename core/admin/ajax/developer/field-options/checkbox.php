<?
	// Stop notices
	$data["custom_value"] = isset($data["custom_value"]) ? $data["custom_value"] : "";
?>
<fieldset>
	<label>Value <small>(defaults to "on")</small></label>
	<input type="text" name="custom_value" value="<?=htmlspecialchars($data["custom_value"])?>" />
</fieldset>