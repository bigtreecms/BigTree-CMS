<fieldset>
	<input<?=$input_validation_class?> type="checkbox" tabindex="<?=$tabindex?>" name="<?=$key?>" id="field_<?=$key?>" <? if ($value) { ?>checked="checked" <? } ?><? if ($options["custom_value"]) { ?> value="<?=htmlspecialchars($options["custom_value"])?>"<? } ?> />
	<label<?=$label_validation_class?> class="for_checkbox">
		<? if ($title) { ?><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?><? } ?>		
	</label>
</fieldset>