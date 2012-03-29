<?
	$t = $_POST["type"];
	$d = json_decode($_POST["data"],true);
	
	$validation_options = array(
		"required" => "Required",
		"numeric" => "Numeric",
		"numeric required" => "Numeric (required)",
		"email" => "Email",
		"email required" => "Email (required)",
		"link" => "Link",
		"link required" => "Link (required)"
	);
	
	if ($t == "text") {
?>
<fieldset>
	<label>Validation</label>
	<select name="validation">
		<option></option>
		<? foreach ($validation_options as $k => $v) { ?>
		<option value="<?=$k?>"<? if ($k == $d["validation"]) { ?> selected="selected"<? } ?>><?=$v?></option>
		<? } ?>
	</select>
</fieldset>
<?
	} elseif ($t == "textarea" || $t == "upload" || $t == "html" || $t == "list") {
?>
<fieldset>
	<label>Validation</label>
	<input type="checkbox" name="validation" value="required" /> Required
</fieldset>
<?	
	}

	if (file_exists(BigTree::path("admin/ajax/developer/field-options/".$t.".php"))) {
		include BigTree::path("admin/ajax/developer/field-options/".$t.".php");
	}
	
	if ($t != "geocoding" && $t != "route") {
?>
<fieldset>
	<label>Field Wrapper <small>(enter full tag, i.e. &lt;div class="test"&gt;)</small></label>
	<input type="text" name="wrapper" value="<?=htmlspecialchars($d["wrapper"])?>" />
</fieldset>
<?
	}
?>
<script type="text/javascript">
	$(".table_select").change(function() {
		name = $(this).attr("name");
		table = $(this).val();
		$(".pop-dependant").each(function(el) {
			if ($(this).hasClass(name)) {
				if ($(this).hasClass("sort_by")) {
					$(this).load("<?=$admin_root?>ajax/developer/load-table-columns/?sort=true&table=" + table + "&field=" + $(this).attr("name"));
				} else {
					$(this).load("<?=$admin_root?>ajax/developer/load-table-columns/?table=" + table + "&field=" + $(this).attr("name"));
				}
			}
		});
	});
</script>