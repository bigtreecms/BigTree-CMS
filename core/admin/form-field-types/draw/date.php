<?
	if (!$field["value"] && isset($field["options"]["default_today"]) && $field["options"]["default_today"]) {
		$field["value"] = date("m/d/Y");
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		$bigtree["datepickers"][] = $field["id"];
		$bigtree["datepicker_values"][$field["id"]] = $field["value"];	
?>
<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
<div id="<?=$field["id"]?>"></div>
<?
	} else {
		$bigtree["datepickers"][] = $field["id"];
?>
<input type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<? if ($field["value"] && $field["value"] != "0000-00-00" && $field["value"] != "0000-00-00 00:00:00") { echo date("m/d/Y",strtotime($field["value"])); } ?>" autocomplete="off" id="<?=$field["id"]?>" class="date_picker<? if ($field["required"]) { ?> required<? } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?
	}
?>