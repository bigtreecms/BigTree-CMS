<?
	// Stop notices
	$data["seo_h1"] = isset($data["seo_h1"]) ? $data["seo_h1"] : "";
	$data["sub_type"] = isset($data["sub_type"]) ? $data["sub_type"] : "";
	$data["max_length"] = isset($data["max_length"]) ? $data["max_length"] : "";
	
	$sub_types = array(
		"" => "",
		"name" => "Name",
		"address" => "Address",
		"email" => "Email",
		"website" => "Website",
		"phone" => "Phone Number"
	);
?>
<fieldset>
	<label>Sub Type</label>
	<select name="sub_type">
		<? foreach ($sub_types as $type => $desc) { ?>
		<option value="<?=$type?>"<? if ($type == $data["sub_type"]) { ?> selected="selected"<? } ?>><?=$desc?></option>
		<? } ?>
	</select>
</fieldset>
<fieldset>
	<label>Maximum Character Length <small>(leave empty or 0 for no max)</small></label>
	<input type="text" placeholder="0" name="max_length" value="<?=$data["max_length"]?>" />
</fieldset>
<? if (isset($_POST["template"])) { ?>
<fieldset>
	<input type="checkbox" name="seo_h1"<? if ($data["seo_h1"]) { ?> checked="checked"<? } ?> />
	<label class="for_checkbox">Use For &lt;H1&gt; SEO Score <small>(only a single field can be used)</small></label>
</fieldset>
<? } ?>