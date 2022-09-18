<?php
	$key = $_POST["key"];
	$count = $_POST["count"];
	$tabindex = $_POST["tab_index"] ?? 1;
	$cached_types = $admin->getCachedFieldTypes();

	$bigtree["field_types"] = $cached_types["callouts"];
	$bigtree["field_namespace"] = uniqid("callout_field_");
	$bigtree["html_fields"] = [];
	$bigtree["simple_html_fields"] = [];
	$bigtree["matrix_columns"] = $_POST["columns"];

	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}
?>
<div class="inner">
	<span class="icon_sort"></span>
	<p class="multi_widget_entry_title">New Entry</p>
	<a href="#" class="icon_delete"></a>
	<a href="#" class="icon_edit"></a>
</div>
	
<div class="matrix_entry_fields">
	<?php
		foreach ($bigtree["matrix_columns"] as $resource) {
			if (!empty($resource["settings"])) {
				$settings = @json_decode($resource["settings"], true);
			} else if (!empty($resource["options"])) {
				$settings = @json_decode($resource["options"], true);
			} else {
				$settings = [];
			}
			
			if (!is_array($settings)) {
				$settings = [];
			}

			if (empty($settings["directory"])) {
				$settings["directory"] = "files/pages/";
			}

			$field = [
				"type" => $resource["type"],
				"title" => $resource["title"] ?? "",
				"subtitle" => $resource["subtitle"] ?? "",
				"key" => $key."[$count][".$resource["id"]."]",
				"tabindex" => $tabindex,
				"settings" => $settings,
				"matrix_title_field" => !empty($resource["display_title"]),
				"value" => "",
			];

			BigTreeAdmin::drawField($field);
		}
	?>

	<button class="matrix_collapse button green">Done Editing</button>
</div>
<?php
	if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
		$bigtree["html_editor_height"] = 365;
		include BigTree::path("admin/layouts/_html-field-loader.php");
	}
	
	include BigTree::path("admin/layouts/_ajax-ready-loader.php");
?>