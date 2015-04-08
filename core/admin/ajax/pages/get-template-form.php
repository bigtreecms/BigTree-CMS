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
		$bigtree["html_fields"] = array();
		$bigtree["simple_html_fields"] = array();
		$bigtree["tabindex"] = 1;
		$bigtree["field_namespace"] = uniqid("template_field_");
		$bigtree["field_counter"] = 0;
		// We alias $bigtree["entry"] to $bigtree["resources"] so that information is in the same place for field types.
		$bigtree["entry"] = &$bigtree["resources"];
	
		if (is_array($bigtree["template"]["resources"]) && count($bigtree["template"]["resources"])) {
			foreach ($bigtree["template"]["resources"] as $resource) {
				$field = array(
					"type" => $resource["type"],
					"title" => $resource["title"],
					"subtitle" => $resource["subtitle"],
					"key" => "resources[".$resource["id"]."]",
					"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
					"tabindex" => $bigtree["tabindex"],
					"options" => $resource["options"]
				);
	
				BigTreeAdmin::drawField($field);
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
	BigTree.TinyMCEFields = <?=json_encode($bigtree["tinymce_fields"])?>;
</script>