<?
	// Draw field types as callout resources
	define("BIGTREE_CALLOUT_RESOURCES",true);

	$bigtree["matrix_count"] = $_POST["count"];
	$bigtree["matrix_key"] = $_POST["key"];
	$bigtree["matrix_columns"] = $_POST["columns"];

	$bigtree["resources"] = isset($_POST["data"]) ? json_decode(base64_decode($_POST["data"]),true) : array();
	foreach ($bigtree["resources"] as &$val) {
		if (is_array($val)) {
			$val = BigTree::untranslateArray($val);
		} elseif (is_array(json_decode($val,true))) {
			$val = BigTree::untranslateArray(json_decode($val,true));
		} else {
			$val = $cms->replaceInternalPageLinks($val);
		}
	}
	unset($val);

	$bigtree["tabindex"] = 1000;	
	$bigtree["datepickers"] = array();
	$bigtree["datepicker_values"] = array();
	$bigtree["timepickers"] = array();
	$bigtree["timepicker_values"] = array();
	$bigtree["datetimepickers"] = array();
	$bigtree["datetimepicker_values"] = array();
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["callouts"];
?>
<div id="matrix_resources" class="callout_fields">
	<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
	<div class="form_fields">
		<?
			if (count($bigtree["matrix_columns"])) {
				foreach ($bigtree["matrix_columns"] as $resource) {
					$field = array();
					// Leaving some variable settings for backwards compatibility — removing in 5.0
					$field["title"] = $title = htmlspecialchars($resource["title"]);
					$field["subtitle"] = $subtitle = htmlspecialchars($resource["subtitle"]);
					$field["key"] = $key = $bigtree["matrix_key"]."[".$bigtree["matrix_count"]."][".$resource["id"]."]";
					$field["value"] = $value = isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "";
					$field["id"] = uniqid("field_");
					$field["tabindex"] = $tabindex = $bigtree["tabindex"];
	
					$options = @json_decode($resource["options"],true);
					$options = is_array($options) ? $options : array();
					$field["options"] = $options;
		
					// Setup Validation Classes
					$label_validation_class = "";
					$field["required"] = false;
					if (isset($options["validation"]) && $options["validation"]) {
						if (strpos($options["validation"],"required") !== false) {
							$label_validation_class = ' class="required"';
							$field["required"] = true;
						}
					}
					$field_type_path = BigTree::path("admin/form-field-types/draw/".$resource["type"].".php");
					
					if (file_exists($field_type_path)) {
		?>
		<fieldset<? if ($resource["display_title"]) { ?> class="bigtree_matrix_display_title"<? } ?>>
			<?
						if ($field["title"] && $resource["type"] != "checkbox") {
			?>
			<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
			<?
						}
						include $field_type_path;
			?>
		</fieldset>
		<?
						$bigtree["tabindex"]++;
					}
				}
			} else {
				echo '<p>There are no resources for the selected callout.</p>';
			}
		?>
	</div>
</div>
<input type="hidden" name="<?=$bigtree["matrix_key"]?>[<?=$bigtree["matrix_count"]?>][matrix_count]" class="matrix_count" value="<?=$bigtree["matrix_count"]?>" />
<script>	
	<?
		foreach ($bigtree["timepickers"] as $id) {
			if ($bigtree["timepicker_values"][$id]) {
				$time = strtotime($bigtree["timepicker_values"][$id]);
			} else {
				$time = strtotime("January 1, 2011 12:00am");
			}
	?>
	$("#<?=$id?>").timepicker({ hour: <?=date("H",$time)?>, minute: <?=date("i",$time)?>, ampm: true, hourGrid: 6, minuteGrid: 10, onSelect: function(dateText) { $("#<?=$id?>").prev("input").val(dateText); } });
	<?
		}
		
		$date_format = BigTree::phpDateTojQuery($bigtree["config"]["date_format"]);
		foreach ($bigtree["datepickers"] as $id) {
			if ($bigtree["datepicker_values"][$id]) {
				$date = date($bigtree["config"]["date_format"],strtotime($bigtree["datepicker_values"][$id]));
			} else {
				$date = date($bigtree["config"]["date_format"]);
			}
	?>
	$("#<?=$id?>").datepicker({ dateFormat: "<?=$date_format?>", defaultDate: "<?=$date?>", onSelect: function(dateText) { $("#<?=$id?>").prev("input").val(dateText); } });
	<?
		}

		foreach ($bigtree["datetimepickers"] as $id) {
			$time = strtotime($bigtree["datetimepicker_values"][$id]["time"]);
			$date = date($bigtree["config"]["date_format"],strtotime($bigtree["datetimepicker_values"][$id]["date"]));
	?>
	$("#<?=$id?>").datetimepicker({ hour: <?=date("H",$time)?>, minute: <?=date("i",$time)?>, ampm: true, hourGrid: 6, minuteGrid: 10, defaultDate: "<?=$date?>", onSelect: function(dateText) { $("#<?=$id?>").prev("input").val(dateText); } });
	<?
		}
	?>
</script>
<?
	$bigtree["html_editor_width"] = 440;
	$bigtree["html_editor_height"] = 200;	
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>