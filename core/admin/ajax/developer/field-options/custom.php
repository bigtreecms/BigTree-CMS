<?
	// Stop notices
	$data["function"] = isset($data["function"]) ? $data["function"] : "";
	$data["process_function"] = isset($data["process_function"]) ? $data["process_function"] : "";
?>
<fieldset>
	<label>Drawing Call <small>(function name only, receives $key, $value, $title, $subtitle, $tabindex)</small></label>
	<input type="text" name="function" value="<?=htmlspecialchars($data["function"])?>" />
</fieldset>
<fieldset>
	<label>Processing Call <small>(optional, function name only, receives: $value, $key)</small></label>
	<input type="text" name="process_function" value="<?=htmlspecialchars($data["process_function"])?>" />
</fieldset>