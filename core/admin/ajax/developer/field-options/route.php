<fieldset>
	<label>Source Field</label>
	<input type="text" name="source" value="<?=htmlspecialchars($data["source"])?>" />
</fieldset>

<fieldset>
	<label>Disregard Uniqueness<small>(if this box is checked duplicate routes can exist)</small></label>
	<input type="checkbox" name="not_unique" <? if ($data["not_unique"]) { ?>checked="checked" <? } ?>/> Enabled
</fieldset>

<fieldset>
	<label>Keep Original Route<small>(check to keep the first generated route)</small></label>
	<input type="checkbox" name="keep_original" <? if ($data["keep_original"]) { ?>checked="checked" <? } ?>/> Enabled
</fieldset>