<?php
	namespace BigTree;

	// Stop notices
	$data["seo_h1"] = isset($data["seo_h1"]) ? $data["seo_h1"] : "";
	$data["sub_type"] = isset($data["sub_type"]) ? $data["sub_type"] : "";
	$data["max_length"] = isset($data["max_length"]) ? $data["max_length"] : "";
	
	$sub_types = array(
		"" => "",
		"name" => Text::translate("Name"),
		"address" => Text::translate("Address"),
		"email" => Text::translate("Email"),
		"website" => Text::translate("Website"),
		"phone" => Text::translate("Phone Number")
	);
?>
<fieldset>
	<label><?=Text::translate("Sub Type")?></label>
	<select name="sub_type">
		<?php foreach ($sub_types as $type => $desc) { ?>
		<option value="<?=$type?>"<?php if ($type == $data["sub_type"]) { ?> selected="selected"<?php } ?>><?=$desc?></option>
		<?php } ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Maximum Character Length <small>(leave empty or 0 for no max)</small>")?></label>
	<input type="text" placeholder="0" name="max_length" value="<?=$data["max_length"]?>" />
</fieldset>
<?php if (isset($_POST["template"])) { ?>
<fieldset>
	<input type="checkbox" name="seo_h1"<?php if ($data["seo_h1"]) { ?> checked="checked"<?php } ?> />
	<label class="for_checkbox"><?=Text::translate("Use For &lt;H1&gt; SEO Score <small>(only a single field can be used)</small>")?></label>
</fieldset>
<?php } ?>