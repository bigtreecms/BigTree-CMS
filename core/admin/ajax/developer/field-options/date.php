<?
	// Stop notices
	$data["default_today"] = isset($data["default_today"]) ? $data["default_today"] : "";
?>
<fieldset>
	<input type="checkbox" name="default_today"<? if ($data["default_today"]) { ?> checked="checked"<? } ?>/>
	<label class="for_checkbox">Default to Today's Date</label>
</fieldset>