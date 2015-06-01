<?php
	// Prevent directory path shenanigans
	$field_type = BigTree::cleanFile($_POST["type"]);
	$data = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);
	
	$validation_options = array(
		"required" => "Required",
		"numeric" => "Numeric",
		"numeric required" => "Numeric (required)",
		"email" => "Email",
		"email required" => "Email (required)",
		"link" => "Link",
		"link required" => "Link (required)"
	);
	
	$validation = isset($data["validation"]) ? $data["validation"] : "";
	
	if ($field_type == "text") {
?>
<fieldset>
	<label>Validation</label>
	<select name="validation">
		<option></option>
		<?php foreach ($validation_options as $k => $v) { ?>
		<option value="<?=$k?>"<?php if ($k == $validation) { ?> selected="selected"<?php } ?>><?=$v?></option>
		<?php } ?>
	</select>
</fieldset>
<?php
	} elseif ($field_type == "textarea" || $field_type == "upload" || $field_type == "html" || $field_type == "list" || $field_type == "time" || $field_type == "date" || $field_type == "datetime" || $field_type == "checkbox") {
?>
<fieldset>
	<input type="checkbox" name="validation" value="required"<?php if ($validation == "required") { ?> checked="checked"<?php } ?> />
	<label class="for_checkbox">Required</label>
</fieldset>
<?php
	}

	// Check for extension field type
	if (strpos($field_type,"*") === false) {
		$path = BigTree::path("admin/ajax/developer/field-options/$field_type.php");
	} else {
		list($extension,$field_type) = explode("*",$field_type);
		$path = SERVER_ROOT."extensions/$extension/field-types/$field_type/options.php";	
	}

	if (file_exists($path)) {
		if ($field_type == "text" || $field_type == "textarea" || $field_type == "upload" || $field_type == "html" || $field_type == "list") {
			echo "<hr />";
		}
		include $path;
	} else {
		if ($field_type != "text" && $field_type != "textarea" && $t = "upload" && $field_type != "html" && $field_type != "list" && $field_type != "time" && $field_type != "date" && $field_type != "datetime") {
?>
<p>This field type does not have any options.</p>
<?php
		}
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