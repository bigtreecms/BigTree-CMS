<? if ($_POST["template"]) { ?>
<fieldset>
	<label>Use For Body Copy SEO Score</label>
	<input type="checkbox" name="seo_body"<? if ($d["seo_body"]) { ?> checked="checked"<? } ?> /> Enabled
</fieldset>
<? } ?>
<fieldset>
	<label>Simple Mode <small>(less options)</small></label>
	<input type="checkbox" name="simple"<? if ($d["simple"]) { ?> checked="checked"<? } ?> /> Enabled
</fieldset>