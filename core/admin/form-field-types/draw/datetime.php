<?
	if (!$value && isset($options["default_now"]) && $options["default_now"]) {
		$value = date("m/d/Y g:i a");
	}
	
	$validation = isset($options["validation"]) ? " ".$options["validation"] : "";
?>
<fieldset>
	<?
		if ($title) {
	?>
	<label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label>
	<?
		}
		
		if ($bigtree["in_callout"]) {
			$clean_key = str_replace(array("[","]"),"_",$key);
			$bigtree["datetimepickers"][] = "field_$clean_key";
			if ($value) {
				$bigtree["datetimepicker_values"]["field_$clean_key"] = array("date" => date("m/d/Y",strtotime($value)), "time" => date("g:i a",strtotime($value)));
			} else {
				$bigtree["datetimepicker_values"]["field_$clean_key"] = array("date" => "", "time" => "");
			}
	?>
	<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
	<div id="field_<?=$clean_key?>"></div>
	<?
		} else {
			$bigtree["datetimepickers"][] = "field_$key";
	?>
	<input type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<? if ($value) { echo date("m/d/Y h:i a",strtotime($value)); } ?>" autocomplete="off" id="field_<?=$key?>" class="date_picker<?=$validation?>" />
	<span class="icon_small icon_small_calendar date_picker_icon"></span>
	<?
		}
	?>
</fieldset>