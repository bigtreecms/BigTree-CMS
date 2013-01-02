<?
	// Stop notices
	$data["default_now"] = isset($data["default_now"]) ? $data["default_now"] : "";
?>
<fieldset>
	<label>Default to Today's Date &amp; Time</label>
	<select name="default_now">
		<option value="">No</option>
		<option value="on"<? if ($data["default_now"]) { ?> selected="selected"<? } ?>>Yes</option>
	</select>
</fieldset>