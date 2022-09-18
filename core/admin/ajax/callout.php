<?php
	$callout = $admin->getCallout($_POST["type"]);
	$key = $_POST["key"];
	$count = $_POST["count"];
	$tabindex = $_POST["tab_index"];
	$existing_data = $_POST["data"] ?? [];
	$cached_types = $admin->getCachedFieldTypes();

	$bigtree["field_types"] = $cached_types["callouts"];
	$bigtree["field_namespace"] = uniqid("callout_field_");
	$bigtree["html_fields"] = [];
	$bigtree["simple_html_fields"] = [];

	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}
?>
<div class="inner">
	<span class="icon_sort"></span>
	<p class="multi_widget_entry_title"><?=$callout["name"]?></p>
	<a href="#" class="icon_delete"></a>
	<a href="#" class="icon_edit"></a>
</div>
	
<div class="matrix_entry_fields callout_fields">
	<input type="hidden" name="<?=$key?>[<?=$count?>][type]" value="<?=$callout["id"]?>">

	<?php
		// Run hooks for modifying the field array
		$callout["resources"] = $admin->runHooks("fields", "callout", $callout["resources"], [
			"callout" => $callout,
			"step" => "draw"
		]);
		
		$bigtree["callout"] = $callout;
		
		foreach ($callout["resources"] as $resource) {
			$field = [
				"type" => $resource["type"],
				"title" => $resource["title"],
				"subtitle" => $resource["subtitle"],
				"key" => $key."[$count][".$resource["id"]."]",
				"tabindex" => $tabindex,
				"settings" => $resource["settings"] ?? $resource["options"] ?? [],
				"value" => $existing_data[$resource["id"]] ?? "",
				"has_value" => isset($existing_data[$resource["id"]]),
			];

			if (empty($field["settings"]["directory"])) {
				$field["settings"]["directory"] = "files/callouts/";
			}

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