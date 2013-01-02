<?
	if (!$value && isset($options["default_today"]) && $options["default_today"]) {
		$value = date("m/d/Y");
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
			$bigtree["datepickers"][] = "field_$clean_key";
			$bigtree["datepicker_values"]["field_$clean_key"] = $value;	
	?>
	<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
	<div id="field_<?=$clean_key?>"></div>
	<?
		} else {
			$bigtree["datepickers"][] = "field_$key";
	?>
	<input type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<? if ($value) { echo date("m/d/Y",strtotime($value)); } ?>" autocomplete="off" id="field_<?=$key?>" class="date_picker<?=$validation?>" />
	<span class="icon_small icon_small_calendar date_picker_icon"></span>
	<?
		}
	?>
</fieldset>