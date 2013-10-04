<?
	if (!$field["value"] && isset($field["options"]["default_now"]) && $field["options"]["default_now"]) {
		$field["value"] = date("m/d/Y g:i a");
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		$bigtree["datetimepickers"][] = $field["id"];
		if ($field["value"] && $field["value"] != "0000-00-00 00:00:00") {
			$bigtree["datetimepicker_values"][$field["id"]] = array("date" => date("m/d/Y",strtotime($field["value"])), "time" => date("g:i a",strtotime($field["value"])));
		} else {
			$bigtree["datetimepicker_values"][$field["id"]] = array("date" => date("m/d/Y"), "time" => date("g:i a"));
		}
?>
<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
<div id="<?=$field["id"]?>"></div>
<?
	} else {
		$bigtree["datetimepickers"][] = $field["id"];
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<? if ($field["value"] && $field["value"] != "0000-00-00 00:00:00") { echo date("m/d/Y h:i a",strtotime($field["value"])); } ?>" autocomplete="off" id="<?=$field["id"]?>" class="date_picker<? if ($field["required"]) { ?> required<? } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?
	}
?>