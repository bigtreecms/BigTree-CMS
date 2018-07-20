<?php
	// Draw field types as callout resources
	define("BIGTREE_CALLOUT_RESOURCES",true);

	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}

	$bigtree["matrix_count"] = intval($_POST["count"]);
	$bigtree["matrix_key"] = htmlspecialchars($_POST["key"]);
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
?>
<div id="matrix_resources" class="callout_fields">
	<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
	<div class="form_fields">
		<?php
			if (count($bigtree["matrix_columns"])) {

				$bigtree["tabindex"] = 1000 * intval($_POST["tab_depth"]);
				$bigtree["html_fields"] = array();
				$bigtree["simple_html_fields"] = array();
				$bigtree["field_namespace"] = uniqid("matrix_field_");
				$bigtree["field_counter"] = 0;
			
				$cached_types = $admin->getCachedFieldTypes();
				$bigtree["field_types"] = $cached_types["callouts"];

				foreach ($bigtree["matrix_columns"] as $resource) {
					$settings = $resource["settings"] ? @json_decode($resource["settings"], true) : @json_decode($resource["options"],true);
					
					$field = array(
						"type" => $resource["type"],
						"title" => htmlspecialchars($resource["title"]),
						"subtitle" => htmlspecialchars($resource["subtitle"]),
						"key" => $bigtree["matrix_key"]."[".$bigtree["matrix_count"]."][".$resource["id"]."]",
						"has_value" => isset($bigtree["resources"][$resource["id"]]),
						"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
						"tabindex" => $bigtree["tabindex"],
						"settings" => is_array($settings) ? $settings : array(),
						"matrix_title_field" => $resource["display_title"] ? true : false
					);

					BigTreeAdmin::drawField($field);
				}
			} else {
				echo '<p>There are no resources for the selected callout.</p>';
			}
		?>
	</div>
</div>
<?php
	$bigtree["html_editor_width"] = 440;
	$bigtree["html_editor_height"] = 200;	
	include BigTree::path("admin/layouts/_html-field-loader.php");
	include BigTree::path("admin/layouts/_ajax-ready-loader.php");
?>