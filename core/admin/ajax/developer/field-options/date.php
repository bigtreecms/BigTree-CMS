<?
	// Stop notices
	$data["default_today"] = isset($data["default_today"]) ? $data["default_today"] : "";
?>
<fieldset>
	<label>Default to Today's Date</label>
	<select name="default_today">
		<option value="">No</option>
		<option value="on"<? if ($data["default_today"]) { ?> selected="selected"<? } ?>>Yes</option>
	</select>
</fieldset>