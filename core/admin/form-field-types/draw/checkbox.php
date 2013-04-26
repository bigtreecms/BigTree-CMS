<input<? if ($field["required"]) { ?> class="required"<? } ?> type="checkbox" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" id="<?=$field["id"]?>" <? if ($field["value"]) { ?>checked="checked" <? } ?><? if ($field["options"]["custom_value"]) { ?> value="<?=htmlspecialchars(htmlspecialchars_decode($field["options"]["custom_value"]))?>"<? } ?> />
<? if ($field["title"]) { ?>
<label<? if ($field["required"]) { ?> class="required"<? } ?> class="for_checkbox" for="<?=$field["id"]?>">
	<?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?>
</label>
<? } ?>