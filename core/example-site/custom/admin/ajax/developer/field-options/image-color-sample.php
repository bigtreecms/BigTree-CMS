<?
	// Stop notices
	$data["source"] = isset($data["source"]) ? $data["source"] : "";
?>
<fieldset>
	<label>Image Source Field</label>
	<input type="text" name="source" value="<?=htmlspecialchars($data["source"])?>" />
</fieldset>