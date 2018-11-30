<?php
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

	if (isset($_POST["page"]) && $template_id != $bigtree["current_page"]["template"]) {
		$original_template = $cms->getTemplate($bigtree["current_page"]["template"]);
		$forced_recrops = $admin->rectifyResourceTypeChange($bigtree["resources"], $bigtree["template"]["resources"], $original_template["resources"]);
	} else {
		$forced_recrops = [];
	}

	// See if we have an editing hook
	if (!empty($bigtree["template"]["hooks"]["edit"])) {
		$bigtree["resources"] = call_user_func($bigtree["template"]["hooks"]["edit"], $bigtree["resources"], $bigtree["template"], true);
	}
?>
<div class="alert template_message">
	<label>Template:</label>
	<p><?php if ($template_id == "") { ?>External Link<?php } elseif ($template_id == "!") { ?>Redirect Lower<?php } else { ?><?=$bigtree["template"]["name"]?><?php } ?></p>
</div>
<?php $admin->drawPOSTErrorMessage(); ?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<div class="form_fields">
	<?php
		$bigtree["html_fields"] = array();
		$bigtree["simple_html_fields"] = array();
		$bigtree["tabindex"] = 11;
		$bigtree["field_namespace"] = uniqid("template_field_");
		$bigtree["field_counter"] = 0;
		// We alias $bigtree["entry"] to $bigtree["resources"] so that information is in the same place for field types.
		$bigtree["entry"] = &$bigtree["resources"];

		$bigtree["template"]["resources"] = $admin->runHooks("fields", "template", $bigtree["template"]["resources"], [
			"template" => $bigtree["template"],
			"step" => "draw",
			"page" => $bigtree["current_page"]
		]);
	
		if (is_array($bigtree["template"]["resources"]) && count($bigtree["template"]["resources"])) {
			foreach ($bigtree["template"]["resources"] as $resource) {
				$field = array(
					"type" => $resource["type"],
					"title" => $resource["title"],
					"subtitle" => $resource["subtitle"],
					"key" => "resources[".$resource["id"]."]",
					"has_value" => isset($bigtree["resources"][$resource["id"]]),
					"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
					"tabindex" => $bigtree["tabindex"],
					"settings" => $resource["settings"] ?: $resource["options"],
					"forced_recrop" => isset($forced_recrops[$resource["id"]]) ? true : false
				);
	
				BigTreeAdmin::drawField($field);
			}
		} else {
			echo '<p>There are no resources for the selected template.</p>';
		}
	?>
</div>
<?php
	$bigtree["html_editor_width"] = 898;
	$bigtree["html_editor_height"] = 365;
	include BigTree::path("admin/layouts/_html-field-loader.php");
	$bigtree["tinymce_fields"] = array_merge($bigtree["html_fields"],$bigtree["simple_html_fields"]);
?>
<script>
	BigTree.TinyMCEFields = <?=json_encode($bigtree["tinymce_fields"])?>;
</script>