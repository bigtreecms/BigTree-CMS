<?
	// Stop notices
	$data["seo_h1"] = isset($data["seo_h1"]) ? $data["seo_h1"] : "";
	$data["sub_type"] = isset($data["sub_type"]) ? $data["sub_type"] : "";
	
	$sub_types = array(
		"" => "",
		"name" => "Name",
		"address" => "Address",
		"email" => "Email",
		"website" => "Website",
		"phone" => "Phone Number"
	);
?>
<? if (isset($_POST["template"])) { ?>
<fieldset>
	<label>Use For &lt;H1&gt; SEO Score <small>(only a single field can be used)</small></label>
	<input type="checkbox" name="seo_h1"<? if ($data["seo_h1"]) { ?> checked="checked"<? } ?> /> Enabled
</fieldset>
<? } ?>
<fieldset>
	<label>Sub Type</label>
	<select name="sub_type">
		<? foreach ($sub_types as $type => $desc) { ?>
		<option value="<?=$type?>"<? if ($type == $data["sub_type"]) { ?> selected="selected"<? } ?>><?=$desc?></option>
		<? } ?>
	</select>
</fieldset>
