<?php
	namespace BigTree;
	
	// Prevent directory path shenanigans
	$field_type = FileSystem::getSafePath($_POST["type"]);
	$data = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);	
	$validation = isset($data["validation"]) ? $data["validation"] : "";
	
	if ($field_type == "text") {
		$validation_options = array(
			"required" => Text::translate("Required"),
			"numeric" => Text::translate("Numeric"),
			"numeric required" => Text::translate("Numeric (required)"),
			"email" => Text::translate("Email"),
			"email required" => Text::translate("Email (required)"),
			"link" => Text::translate("Link"),
			"link required" => Text::translate("Link (required)")
		);
?>
<fieldset>
	<label><?=Text::translate("Validation")?></label>
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
	<label class="for_checkbox"><?=Text::translate("Required")?></label>
</fieldset>
<?php
	}

	// Check for extension field type
	if (strpos($field_type,"*") === false) {
		$path = Router::getIncludePath("admin/ajax/developer/field-options/$field_type.php");
	} else {
		list($extension,$field_type) = explode("*",$field_type);
		$path = SERVER_ROOT."extensions/$extension/field-types/$field_type/options.php";	
	}

	if (file_exists($path)) {
		if ($field_type == "text" || $field_type == "textarea" || $field_type == "upload" || $field_type == "html" || $field_type == "list") {
			echo "<hr />";
		}
		include $path;
	} elseif ($field_type != "textarea" && $field_type != "time") {
?>
<p><?=Text::translate("This field type does not have any options.")?></p>
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