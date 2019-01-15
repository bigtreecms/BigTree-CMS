<?php
	// Prevent directory path shenanigans
	$field_type = BigTree::cleanFile($_POST["type"]);

	// I honestly don't know why this was doing weird things with \r and \n but leaving it case it's some kind of legacy support
	if (!empty($_POST["data"])) {
		$settings = json_decode($_POST["data"], true);
		
		if (!is_array($settings)) {
			$settings = json_decode(str_replace(array("\r", "\n"), array('\r', '\n'), $_POST["data"]), true);
		}
	
		$settings = BigTree::untranslateArray($settings);
	} else {
		$settings = array();
	}

	// Backwards compatibility
	$data = $settings;
	
	$validation_options = array(
		"required" => "Required",
		"numeric" => "Numeric",
		"numeric required" => "Numeric (required)",
		"email" => "Email",
		"email required" => "Email (required)",
		"link" => "Link",
		"link required" => "Link (required)"
	);
	
	$validation = isset($settings["validation"]) ? $settings["validation"] : "";
	
	if ($field_type == "text") {
?>
<fieldset>
	<label for="settings_field_validation">Validation</label>
	<select id="settings_field_validation" name="validation">
		<option></option>
		<?php foreach ($validation_options as $k => $v) { ?>
		<option value="<?=$k?>"<?php if ($k == $validation) { ?> selected="selected"<?php } ?>><?=$v?></option>
		<?php } ?>
	</select>
</fieldset>
<?php
	} elseif ($field_type == "textarea" || $field_type == "upload" || $field_type == "image" || $field_type == "video" || $field_type == "link" || $field_type == "html" || $field_type == "list" || $field_type == "time" || $field_type == "date" || $field_type == "datetime" || $field_type == "checkbox" || $field_type == "image-reference" || $field_type == "video-reference" || $field_type == "file-reference") {
?>
<fieldset>
	<input id="settings_field_validation" type="checkbox" name="validation" value="required"<?php if ($validation == "required") { ?> checked="checked"<?php } ?> />
	<label for="settings_field_validation" class="for_checkbox">Required</label>
</fieldset>
<?php
	}

	// Check for extension field type
	if (strpos($field_type, "*") === false) {
		// Prefer the < 4.3 path to preserve overrides
		$path = BigTree::path("admin/ajax/developer/field-options/$field_type.php");

		if (!file_exists($path)) {
			$path = BigTree::path("admin/field-types/$field_type/settings.php");
		}
	} else {
		list($extension, $field_type) = explode("*",$field_type);

		$path = SERVER_ROOT."extensions/$extension/field-types/$field_type/settings.php";

		// < 4.3 filename
		if (!file_exists($path)) {
			$path = SERVER_ROOT."extensions/$extension/field-types/$field_type/options.php";
		}
	}

	if (file_exists($path)) {
		if ($field_type == "text" || $field_type == "textarea" || $field_type == "upload" || $field_type == "html" || $field_type == "list") {
			echo "<hr />";
		}
		include $path;
	} elseif ($field_type != "link" && $field_type != "textarea" && $field_type != "time" && $field_type != "file-reference" && $field_type != "video-reference" && $field_type != "image-reference") {
?>
<p>This field type does not have any settings.</p>
<?php
	}
?>
<script>	
	$(".table_select").change(function() {
		var name = $(this).attr("name");
		var table = $(this).val();
		$(".pop-dependant").each(function(el) {
			if ($(this).hasClass(name)) {
				if ($(this).hasClass("sort_by")) {
					$(this).load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?sort=true&table=" + table + "&field=" + $(this).attr("data-name"));
				} else {
					$(this).load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + table + "&field=" + $(this).attr("data-name"));
				}
			}
		});
	});
</script>