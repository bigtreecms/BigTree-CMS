<?
	// Stop notices
	$data["seo_body"] = isset($data["seo_body"]) ? $data["seo_body"] : "";
	$data["simple"] = isset($data["simple"]) ? $data["simple"] : "";
	
	if (isset($_POST["template"])) {
?>
<fieldset>
	<input type="checkbox" name="seo_body"<? if ($data["seo_body"]) { ?> checked="checked"<? } ?> />
	<label class="for_checkbox">Use For Body Copy SEO Score</label>
</fieldset>
<?
	}
?>
<fieldset>
	<input type="checkbox" name="simple"<? if ($data["simple"]) { ?> checked="checked"<? } ?> />
	<label class="for_checkbox">Simple Mode <small>(less options)</small></label>
</fieldset>