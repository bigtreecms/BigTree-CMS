<?
	$dates[] = "field_$key";
	if (!$value && $options["default_today"])
		$value = date("Y-m-d");
?>
<fieldset>
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<input type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<?=$value?>" autocomplete="off" id="field_<?=$key?>" class="date_picker <?=$options["validation"]?>" />
</fieldset>