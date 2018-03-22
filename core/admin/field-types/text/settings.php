<?php
	// Stop notices
	$settings["seo_h1"] = isset($settings["seo_h1"]) ? $settings["seo_h1"] : "";
	$settings["sub_type"] = isset($settings["sub_type"]) ? $settings["sub_type"] : "";
	$settings["max_length"] = isset($settings["max_length"]) ? $settings["max_length"] : "";
	
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
	<label for="settings_field_sub_type">Sub Type</label>
	<select id="settings_field_sub_type" name="sub_type">
		<?php foreach ($sub_types as $type => $desc) { ?>
		<option value="<?=$type?>"<?php if ($type == $settings["sub_type"]) { ?> selected="selected"<?php } ?>><?=$desc?></option>
		<?php } ?>
	</select>
</fieldset>

<fieldset id="settings_fieldset_max_length"<?php if (!empty($settings["sub_type"])) { ?> style="display: none;"<?php } ?>>
	<label for="settings_field_max_length">Maximum Character Length <small>(leave empty or 0 for no max)</small></label>
	<input id="settings_field_max_length" type="text" placeholder="0" name="max_length" value="<?=$settings["max_length"]?>" />
</fieldset>

<?php
	if (isset($_POST["template"])) {
?>
<fieldset id="settings_fieldset_seo_h1"<?php if (!empty($settings["sub_type"])) { ?> style="display: none;"<?php } ?>>
	<input id="settings_field_seo_h1" type="checkbox" name="seo_h1"<?php if ($settings["seo_h1"]) { ?> checked="checked"<?php } ?> />
	<label for="settings_field_seo_h1" class="for_checkbox">Use For &lt;H1&gt; SEO Score <small>(only a single field can be used)</small></label>
</fieldset>
<?php
	}
?>

<script>
	$("#settings_field_sub_type").change(function(ev) {
		if ($(this).val()) {
			$("#settings_fieldset_max_length, #settings_fieldset_seo_h1").hide();
		} else {
			$("#settings_fieldset_max_length, #settings_fieldset_seo_h1").show();
		}
	});
</script>