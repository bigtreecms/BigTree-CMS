<?php
	if (!$field["value"] && isset($field["settings"]["default_now"]) && $field["settings"]["default_now"]) {
		$field["value"] = $admin->convertTimestampToUser("now", $bigtree["config"]["date_format"]." h:i a");
	} elseif ($field["value"] && $field["value"] != "0000-00-00 00:00:00") {
		$field["value"] = $admin->convertTimestampToUser($field["value"], $bigtree["config"]["date_format"]." h:i a");
	} else {
		$field["value"] = "";
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		// Required and in-line is hard to validate, so default to today's date regardless
		if (!empty($field["required"]) && empty($field["value"])) {
			$field["value"] =  $admin->convertTimestampToUser("now", $bigtree["config"]["date_format"]." h:i a");
		}

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
<?php
		if (empty($field["required"])) {
			echo '<div class="date_picker_clear">Clear Date</div>';
		}
	} else {
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" autocomplete="off" id="<?=$field["id"]?>" class="date_time_picker<?php if ($field["required"]) { ?> required<?php } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?php
	}
?>