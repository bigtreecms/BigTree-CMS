<?
	// Stop notices
	$data["default_now"] = isset($data["default_now"]) ? $data["default_now"] : "";
?>
<fieldset>
	<input type="checkbox" name="default_now"<? if ($data["default_now"]) { ?> checked="checked"<? } ?>/>
	<label class="for_checkbox">Default to Today's Date &amp; Time</label>
</fieldset>