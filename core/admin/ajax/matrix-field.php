<?php
	namespace BigTree;
	
	$matrix_index = intval($_POST["index"]);
	$matrix_key = htmlspecialchars($_POST["key"]);
	$matrix_columns = $_POST["columns"];
	$content = [];

	if (isset($_POST["data"])) {
		$content = Link::decode($_POST["data"]);
	}
	
	foreach ($matrix_columns as $column) {
		$settings = is_array($column["settings"]) ? $column["settings"] : @json_decode($column["settings"], true);
		
		$field = new Field([
			"type" => $column["type"],
			"title" => htmlspecialchars($column["title"]),
			"subtitle" => htmlspecialchars($column["subtitle"]),
			"key" => $matrix_key."[entry_".$matrix_index."][".$column["id"]."]",
			"has_value" => isset($content[$column["id"]]),
			"value" => isset($content[$column["id"]]) ? $content[$column["id"]] : "",
			"settings" => is_array($settings) ? $settings : [],
			"feeds_display_title" => !empty($column["display_title"])
		]);
		
		$field->draw();
	}
