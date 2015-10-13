<?
	if (!$field["value"] && isset($field["options"]["default_today"]) && $field["options"]["default_today"]) {
		$field["value"] = date($bigtree["config"]["date_format"]);
	} elseif ($field["value"] && $field["value"] != "0000-00-00" && $field["value"] != "0000-00-00 00:00:00") {
		$field["value"] = date($bigtree["config"]["date_format"],strtotime($field["value"]));
	} else {
		$field["value"] = "";
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
?>
<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
<div class="date_picker_inline" data-date="<?=$field["value"]?>"></div>
<div class="date_picker_clear">Clear Date</div>
<?
	} else {
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" autocomplete="off" id="<?=$field["id"]?>" class="date_picker<? if ($field["required"]) { ?> required<? } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?
	}
?>