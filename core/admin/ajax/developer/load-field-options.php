<?
	$t = $_POST["type"];
	$d = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);
	$data = $d;
	
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
	
	if ($t == "text") {
?>
<fieldset>
	<label>Validation</label>
	<select name="validation">
		<option></option>
		<? foreach ($validation_options as $k => $v) { ?>
		<option value="<?=$k?>"<? if ($k == $validation) { ?> selected="selected"<? } ?>><?=$v?></option>
		<? } ?>
	</select>
</fieldset>
<?
	} elseif ($t == "textarea" || $t == "upload" || $t == "html" || $t == "list" || $t == "time" || $t == "date" || $t == "datetime") {
?>
<fieldset>
	<input type="checkbox" name="validation" value="required"<? if ($validation == "required") { ?> checked="checked"<? } ?> />
	<label class="for_checkbox">Required</label>
</fieldset>
<?	
	}

	if (file_exists(BigTree::path("admin/ajax/developer/field-options/".$t.".php"))) {
		if ($t == "text" || $t == "textarea" || $t == "upload" || $t == "html" || $t == "list") {
			echo "<hr />";
		}
		include BigTree::path("admin/ajax/developer/field-options/".$t.".php");
	} else {
		if ($t != "text" && $t != "textarea" && $t = "upload" && $t != "html" && $t != "list" && $t != "time" && $t != "date" && $t != "datetime") {
?>
<p>This field type does not have any options.</p>
<?
		}
	}
?>
<script>	
	$(".table_select").change(function() {
		name = $(this).attr("name");
		table = $(this).val();
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