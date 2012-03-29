<fieldset>
	<label>Drawing Call <small>(function name only, receives $key, $value, $title, $subtitle, $tabindex)</small></label>
	<input type="text" name="function" value="<?=htmlspecialchars($d["function"])?>" />
</fieldset>
<fieldset>
	<label>Processing Function <small>(optional, function name only, receives: $value, $key)</small></label>
	<input type="text" name="process_function" value="<?=htmlspecialchars($d["process_function"])?>" />
</fieldset>