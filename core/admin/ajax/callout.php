<?php
	$callout = $admin->getCallout($_POST["type"]);
	$key = $_POST["key"];
	$count = $_POST["count"];
	$tabindex = $_POST["tab_index"];

	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}

	$bigtree["html_fields"] = [];
	$bigtree["simple_html_fields"] = [];
?>
<div class="inner">
	<span class="icon_sort"></span>
		<p class="multi_widget_entry_title"><?=$callout["name"]?></p>
		<a href="#" class="icon_delete"></a>
		<a href="#" class="icon_edit"></a>
	</div>
	
	<div class="matrix_entry_fields">
		<input type="hidden" name="<?=$key?>[<?=$count?>][type]" value="<?=$callout["id"]?>">

		<?php
			foreach ($callout["resources"] as $resource) {
				$field = [
					"type" => $resource["type"],
					"title" => $resource["title"],
					"subtitle" => $resource["subtitle"],
					"key" => $key."[$count][".$resource["id"]."]",
					"tabindex" => $tabindex,
					"settings" => $resource["settings"] ?: $resource["options"]
				];

				if (empty($field["settings"]["directory"])) {
					$field["settings"]["directory"] = "files/callouts/";
				}
	
				BigTreeAdmin::drawField($field);
			}
		?>
	</div>
</div>
<?php
	if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
		$bigtree["html_editor_height"] = 365;
		include BigTree::path("admin/layouts/_html-field-loader.php");
	}
?>