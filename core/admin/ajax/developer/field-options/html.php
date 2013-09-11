<?
	// Stop notices
	$data["seo_body"] = isset($data["seo_body"]) ? $data["seo_body"] : "";
	$data["simple"] = isset($data["simple"]) ? $data["simple"] : "";
	$sbp = isset($data["simple_by_permission"]) ? $data["simple_by_permission"] : "";
	
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
<hr />
<fieldset>
	<label>Simple Mode Via Permissions <small>(minimum access level)</small></label>
	<select name="simple_by_permission">
		<option value="0"></option>
		<option value="1"<? if ($sbp == "1") { ?> selected="selected"<? } ?>>Administrator</option>
		<option value="2"<? if ($sbp == "2") { ?> selected="selected"<? } ?>>Developer</option>
	</select>
</fieldset>
<p class="note">If a user is below this permission level, this HTML area will switch to Simple Mode.</p>
