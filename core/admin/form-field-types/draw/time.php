<fieldset>
	<?
		if ($title) {
	?>
	<label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label>
	<?
		}
		
		if (isset($bigtree["in_callout"])) {
			$clean_key = str_replace(array("[","]"),"_",$key);
			$bigtree["timepicker_values"]["field_$clean_key"] = $value;		
			$bigtree["timepickers"][] = "field_$clean_key";
	?>
	<input type="hidden" name="<?=$key?>" value="<? if ($value) { echo date("h:i a",strtotime($value)); } ?>" />
	<div id="field_<?=$clean_key?>"></div>
	<?		
		} else {
			$bigtree["timepickers"][] = "field_$key";
	?>
	<input type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<? if ($value) { echo date("h:i a",strtotime($value)); } ?>" autocomplete="off" id="field_<?=$key?>" class="time_picker <?=$options["validation"]?>" />
	<span class="icon_small icon_small_clock time_picker_icon"></span>
	<?
		}
	?>
</fieldset>