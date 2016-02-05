<?
	if (!$field["value"] && isset($field["options"]["default_now"]) && $field["options"]["default_now"]) {
		$field["value"] = date($bigtree["config"]["date_format"]." h:i a");
	} elseif ($field["value"] && $field["value"] != "0000-00-00 00:00:00") {
		$field["value"] = date($bigtree["config"]["date_format"]." h:i a",strtotime($field["value"]));
	} else {
		$field["value"] = "";
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		// Process hour/minute
		if ($field["value"]) {
			$date = DateTime::createFromFormat($bigtree["config"]["date_format"]." h:i a",$field["value"]);
			$hour = $date->format("H");
			$minute = $date->format("i");
		} else {
			$hour = "0";
			$minute = "0";
		}
?>
<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
<div class="date_time_picker_inline" data-date="<?=$field["value"]?>" data-hour="<?=$hour?>" data-minute="<?=$minute?>"></div>
<div class="date_picker_clear">Clear Date</div>
<?
	} else {
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" autocomplete="off" id="<?=$field["id"]?>" class="date_time_picker<? if ($field["required"]) { ?> required<? } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?
	}
?>