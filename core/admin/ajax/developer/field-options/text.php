<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["seo_h1"] = isset($options["seo_h1"]) ? $options["seo_h1"] : "";
	$options["sub_type"] = isset($options["sub_type"]) ? $options["sub_type"] : "";
	$options["max_length"] = isset($options["max_length"]) ? $options["max_length"] : "";
	
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
	<label for="options_field_subtype"><?=Text::translate("Sub Type")?></label>
	<select id="options_field_subtype" name="sub_type">
		<?php foreach ($sub_types as $type => $desc) { ?>
		<option value="<?=$type?>"<?php if ($type == $options["sub_type"]) { ?> selected="selected"<?php } ?>><?=$desc?></option>
		<?php } ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_length"><?=Text::translate("Maximum Character Length <small>(leave empty or 0 for no max)</small>")?></label>
	<input id="options_field_length" type="text" placeholder="0" name="max_length" value="<?=$options["max_length"]?>" />
</fieldset>
<?php if (isset($_POST["template"])) { ?>
<fieldset>
	<input id="options_field_seo" type="checkbox" name="seo_h1"<?php if ($options["seo_h1"]) { ?> checked="checked"<?php } ?> />
	<label for="options_field_seo" class="for_checkbox"><?=Text::translate("Use For &lt;H1&gt; SEO Score <small>(only a single field can be used)</small>")?></label>
</fieldset>
<?php } ?>