<?php
	// Stop notices
	$data["seo_body"] = isset($data["seo_body"]) ? $data["seo_body"] : "";
	$data["simple"] = isset($data["simple"]) ? $data["simple"] : "";
	$sbp = isset($data["simple_by_permission"]) ? $data["simple_by_permission"] : "";
	
	if (isset($_POST["template"])) {
?>
<fieldset>
	<input type="checkbox" name="seo_body"<?php if ($data["seo_body"]) { ?> checked="checked"<?php } ?> />
	<label class="for_checkbox">Use For Body Copy SEO Score</label>
</fieldset>
<?php
	}
?>
<fieldset>
	<input type="checkbox" name="simple"<?php if ($data["simple"]) { ?> checked="checked"<?php } ?> />
	<label class="for_checkbox">Simple Mode <small>(less options)</small></label>
</fieldset>
<hr />
<fieldset>
	<label>Simple Mode Via Permissions <small>(minimum access level)</small></label>
	<select name="simple_by_permission">
		<option value="0"></option>
		<option value="1"<?php if ($sbp == "1") { ?> selected="selected"<?php } ?>>Administrator</option>
		<option value="2"<?php if ($sbp == "2") { ?> selected="selected"<?php } ?>>Developer</option>
	</select>
</fieldset>
<p class="note">If a user is below this permission level, this HTML area will switch to Simple Mode.</p>