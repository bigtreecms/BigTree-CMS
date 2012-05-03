<fieldset>
	<label>Source Field</label>
	<input type="text" name="source" value="<?=htmlspecialchars($d["source"])?>" />
</fieldset>

<fieldset>
	<label>Disregard Uniqueness<small>(if this box is checked duplicate routes can exist)</small></label>
	<input type="checkbox" name="not_unique" <? if ($d["not_unique"]) { ?>checked="checked" <? } ?>/> Enabled
</fieldset>

<fieldset>
	<label>Keep Original Route<small>(check to keep the first generated route)</small></label>
	<input type="checkbox" name="keep_original" <? if ($d["keep_original"]) { ?>checked="checked" <? } ?>/> Enabled
</fieldset>