<?php
	if ($field["value"]) {
		$field["value"] = $admin->convertTimestampToUser($field["value"] ?: "now", "h:i a");
	}

	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		$time = strtotime($field["value"] ? $field["value"] : "Today");
?>
<input type="hidden" name="<?=$field["key"]?>" value="<?php if ($field["value"]) { echo date("h:i a",strtotime($field["value"])); } ?>" />
<div class="time_picker_inline" data-hour="<?=date("H",$time)?>" data-minute="<?=date("i",$time)?>"></div>
<?php
	} else {
		$bigtree["timepickers"][] = $field["id"];
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?php if ($field["value"]) { echo date("h:i a",strtotime($field["value"])); } ?>" autocomplete="off" id="<?=$field["id"]?>" class="time_picker<?php if ($field["required"]) { ?> required<?php } ?>" />
<span class="icon_small icon_small_clock time_picker_icon"></span>
<?php
	}
?>