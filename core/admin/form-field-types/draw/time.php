<?
	$times[] = "field_$key";
?>
<fieldset>
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<input type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<? if ($value) { echo date("g:ia",strtotime($value)); } ?>" autocomplete="off" id="field_<?=$key?>" class="time_picker <?=$options["validation"]?>" />
</fieldset>