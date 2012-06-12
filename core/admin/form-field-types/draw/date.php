<?
	$dates[] = "field_$key";
	if (!$value && isset($options["default_today"]) && $options["default_today"]) {
		$value = date("m/d/Y");
	}
	
	$validation = isset($options["validation"]) ? " ".$options["validation"] : "";
?>
<fieldset>
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<input type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<?=$value?>" autocomplete="off" id="field_<?=$key?>" class="date_picker<?=$validation?>" />
</fieldset>