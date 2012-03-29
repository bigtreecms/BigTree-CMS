<fieldset>
	<label>Source Field</label>
	<input type="text" name="source" value="<?=htmlspecialchars($d["source"])?>" />
</fieldset>

<fieldset>
	<label>Disregard Uniqueness<br /><small>(if this box is checked duplicate routes can exist)</small></label>
	<input type="checkbox" name="not_unique" <? if ($d["not_unique"]) { ?>checked="checked" <? } ?>/> Enabled
</fieldset>

<fieldset>
	<label>Keep Original Route<br /><small>(check to keep the first generated route and ignore changes to the source field)</small></label>
	<input type="checkbox" name="keep_original" <? if ($d["keep_original"]) { ?>checked="checked" <? } ?>/> Enabled
</fieldset>