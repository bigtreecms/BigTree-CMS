<?php
	if (!$field["value"] && isset($field["settings"]["default_today"]) && $field["settings"]["default_today"]) {
		$field["value"] = date($bigtree["config"]["date_format"]);
	} elseif ($field["value"] && $field["value"] != "0000-00-00" && $field["value"] != "0000-00-00 00:00:00") {
		$field["value"] = date($bigtree["config"]["date_format"], strtotime($field["value"]));
	} else {
		$field["value"] = "";
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		// Required and in-line is hard to validate, so default to today's date regardless
		if (!empty($field["required"]) && empty($field["value"])) {
			$field["value"] = date($bigtree["config"]["date_format"]);
		}
?>
<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
<div class="date_picker_inline" data-date="<?=$field["value"]?>"></div>
<?php
		if (empty($field["required"])) {
			echo '<div class="date_picker_clear">Clear Date</div>';
		}
	} else {
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" autocomplete="off" id="<?=$field["id"]?>" class="date_picker<?php if ($field["required"]) { ?> required<?php } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?php
	}
?>