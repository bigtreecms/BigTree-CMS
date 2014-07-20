<?
	define("BIGTREE_CALLOUT_RESOURCES",true);
	
	if (isset($_POST["resources"])) {
		$bigtree["resources"] = json_decode(base64_decode($_POST["resources"]),true);
	}
	if (isset($_POST["type"])) {
		$bigtree["resources"]["type"] = $_POST["type"];
	}
	if (isset($_POST["key"])) {
		$bigtree["callout_key"] = $_POST["key"];
	}

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

	$bigtree["callout_count"] = $_POST["count"];
	$bigtree["callout"] = $admin->getCallout($bigtree["resources"]["type"]);

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["callouts"];
	
	if ($bigtree["callout"]["description"]) {
?>
<p class="callout_description"><?=BigTree::safeEncode($bigtree["callout"]["description"])?></p>
<?
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<div class="form_fields">
	<?	
		$bigtree["tabindex"] = 1000;	
		$bigtree["datepickers"] = array();
		$bigtree["datepicker_values"] = array();
		$bigtree["timepickers"] = array();
		$bigtree["timepicker_values"] = array();
		$bigtree["datetimepickers"] = array();
		$bigtree["datetimepicker_values"] = array();
		$bigtree["html_fields"] = array();
		$bigtree["simple_html_fields"] = array();
		
		if (count($bigtree["callout"]["resources"])) {
			$cached_types = $admin->getCachedFieldTypes();
			$bigtree["field_types"] = $cached_types["callouts"];

			foreach ($bigtree["callout"]["resources"] as $resource) {
				$field = array();
				// Leaving some variable settings for backwards compatibility — removing in 5.0
				$field["title"] = $title = $resource["title"];
				$field["subtitle"] = $subtitle = $resource["subtitle"];
				$field["key"] = $key = $bigtree["callout_key"]."[".$bigtree["callout_count"]."][".$resource["id"]."]";
				$field["value"] = $value = isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "";
				$field["id"] = uniqid("field_");
				$field["tabindex"] = $tabindex = $bigtree["tabindex"];
				$field["options"] = $options = $resource;
				$field["options"]["directory"] = "files/pages/"; // File uploads go to /files/pages/
	
				// Setup Validation Classes
				$label_validation_class = "";
				$field["required"] = false;
				if (isset($resource["validation"]) && $resource["validation"]) {
					if (strpos($resource["validation"],"required") !== false) {
						$label_validation_class = ' class="required"';
						$field["required"] = true;
					}
				}
				$field_type_path = BigTree::path("admin/form-field-types/draw/".$resource["type"].".php");
				
				if (file_exists($field_type_path)) {
					if ($bigtree["field_types"][$resource["type"]]["self_draw"]) {
						include $field_type_path;
					} else {
	?>
	<fieldset>
		<?
						if ($field["title"] && $resource["type"] != "checkbox") {
		?>
		<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
		<?
						}
						include $field_type_path;
		?>
	</fieldset>
	<?				}

					$bigtree["tabindex"]++;
				}
			}
		} else {
			echo '<p>There are no resources for the selected callout.</p>';
		}
	?>
</div>
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][display_default]" class="display_default" value="<?=$bigtree["callout"]["display_default"]?>" />
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][display_field]" class="display_field" value="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][<?=$bigtree["callout"]["display_field"]?>]" />
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][callout_count]" class="callout_count" value="<?=$bigtree["callout_count"]?>" />
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
		
		foreach ($bigtree["datepickers"] as $id) {
			if ($bigtree["datepicker_values"][$id]) {
				$date = date("m/d/Y",strtotime($bigtree["datepicker_values"][$id]));
			} else {
				$date = date("m/d/Y");
			}
	?>
	$("#<?=$id?>").datepicker({ defaultDate: "<?=$date?>", onSelect: function(dateText) { $("#<?=$id?>").prev("input").val(dateText); } });
	<?
		}

		foreach ($bigtree["datetimepickers"] as $id) {
			$time = strtotime($bigtree["datetimepicker_values"][$id]["time"]);
			$date = date("m/d/Y",strtotime($bigtree["datetimepicker_values"][$id]["date"]));
	?>
	$("#<?=$id?>").datetimepicker({ hour: <?=date("H",$time)?>, minute: <?=date("i",$time)?>, ampm: true, hourGrid: 6, minuteGrid: 10, defaultDate: "<?=$date?>", onSelect: function(dateText) { $("#<?=$id?>").prev("input").val(dateText); } });
	<?
		}

		// Only re-run if we're loading a new callout type
		if (isset($_POST["type"])) {
	?>
	BigTreeCustomControls($("#callout_resources"));
	<?
		}
	?>
</script>
<?
	$bigtree["html_editor_width"] = 440;
	$bigtree["html_editor_height"] = 200;	
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>