<?
	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["templates"];

	$template_id = $bigtree["current_page"]["template"];
	if (isset($_POST["page"])) {
		$template_id = $_POST["template"];
		$bigtree["current_page"] = $cms->getPendingPage($_POST["page"]);
		$bigtree["resources"] = $bigtree["current_page"]["resources"];
	} elseif (isset($_POST["template"])) {
		$template_id = $_POST["template"];
		$bigtree["resources"] = array();
	} elseif (!isset($bigtree["resources"]) && !isset($bigtree["callouts"])) {
		$bigtree["resources"] = array();
	}

	$bigtree["template"] = $cms->getTemplate($template_id);
?>
<div class="alert template_message">
	<label>Template:</label>
	<p><? if ($template_id == "") { ?>External Link<? } elseif ($template_id == "!") { ?>Redirect Lower<? } else { ?><?=$bigtree["template"]["name"]?><? } ?></p>
</div>
<?
	if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
		unset($_SESSION["bigtree_admin"]["post_max_hit"]);
?>
<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
<?
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<div class="form_fields">
	<?
		$bigtree["datepickers"] = array();
		$bigtree["timepickers"] = array();
		$bigtree["datetimepickers"] = array();
		$bigtree["html_fields"] = array();
		$bigtree["simple_html_fields"] = array();
		$bigtree["tabindex"] = 1;
		// We alias $bigtree["entry"] to $bigtree["resources"] so that information is in the same place for field types.
		$bigtree["entry"] = &$bigtree["resources"];
	
		if (is_array($bigtree["template"]["resources"]) && count($bigtree["template"]["resources"])) {
			foreach ($bigtree["template"]["resources"] as $resource) {
				$field = array();
				// Leaving some variable settings for backwards compatibility — removing in 5.0
				$field["type"] = $resource["type"];
				$field["title"] = $title = $resource["title"];
				$field["subtitle"] = $subtitle = $resource["subtitle"];
				$field["key"] = $key = "resources[".$resource["id"]."]";
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
					// Don't draw the fieldset for the callout type
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
						$bigtree["tabindex"]++;
		?>
	</fieldset>
	<?
					}
					$bigtree["last_resource_type"] = $field["type"];
				}
			}
		} else {
			echo '<p>There are no resources for the selected template.</p>';
		}
	?>
</div>
<?	
	$bigtree["html_editor_width"] = 898;
	$bigtree["html_editor_height"] = 365;
	include BigTree::path("admin/layouts/_html-field-loader.php");
	$bigtree["tinymce_fields"] = array_merge($bigtree["html_fields"],$bigtree["simple_html_fields"]);
?>
<script>
	<?
		foreach ($bigtree["datepickers"] as $id) {
	?>
	$("#<?=$id?>").datepicker({ duration: 200, showAnim: "slideDown" });
	<?
		}
		
		foreach ($bigtree["timepickers"] as $id) {
	?>
	$("#<?=$id?>").timepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6,	minuteGrid: 10 });
	<?
		}
		
		foreach ($bigtree["datetimepickers"] as $id) {
	?>
	$("#<?=$id?>").datetimepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });
	<?
		}
	?>

	BigTree.TinyMCEFields = <?=json_encode($bigtree["tinymce_fields"])?>;
</script>