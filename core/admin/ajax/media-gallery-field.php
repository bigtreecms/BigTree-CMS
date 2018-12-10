<?php
	$settings = $_POST["settings"];
	$settings["validation"] = "required";
	$type = $_POST["type"];
	
	// If we're using a preset, the prefix may be there
	if (!empty($settings["preset"])) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = BigTreeJSONDB::get("config", "media-settings");
		}
		
		$preset = $bigtree["media_settings"]["presets"][$settings["preset"]];
		
		if (!empty($preset["min_width"])) {
			$settings["min_width"] = $preset["min_width"];
		}
		
		if (!empty($preset["min_height"])) {
			$settings["min_height"] = $preset["min_height"];
		}
	}
	
	// We're going to fake post data here so it includes properly in the form
	if (isset($_POST["data"])) {
		$origin_data = json_decode(base64_decode($_POST["data"]), true);
		$type = $origin_data["type"];
		$data = $origin_data["info"];
		
		if ($type == "photo") {
			$data["*photo"] = $origin_data["image"];
		} else {
			$type = $origin_data["video"]["service"];
			
			if ($type == "local") {
				$data["*localvideo"] = $origin_data["video"]["url"];
				$data["*photo"] = $origin_data["image"];
			} else {
				$data["*video"] = $origin_data["video"];
				$data["*video"]["image"] = $origin_data["image"];
			}
		}
		
		// Do weird things to get the values set properly
		$weird_data = [];
		
		foreach ($data as $key => $val) {
			$weird_data["info][".$key] = $val;
		}
		
		$_POST["data"] = base64_encode(json_encode($weird_data));
	}
	
	// Ensure that the columns is an array
	if (!is_array($_POST["columns"])) {
		$_POST["columns"] = [];
	}
	
	if ($type == "photo") {
		$field = [
			"id" => "*photo",
			"type" => "image",
			"title" => "Photo",
			"settings" => $settings
		];
		
		if ($settings["min_width"] && $settings["min_height"]) {
			$field["subtitle"] = "(min ".$settings["min_width"]."x".$settings["min_height"].")";
		}
	} elseif ($type == "local") {
		// Stick it on early - we're giving two fields here
		$field = [
			"id" => "*localvideo",
			"type" => "upload",
			"title" => "Video",
			"subtitle" => "(h264 file)",
			"settings" => '{"validation":"required"}'
		];
		array_unshift($_POST["columns"], $field);
		
		// Second field for the cover photo
		$field = [
			"id" => "*photo",
			"type" => "image",
			"title" => "Cover Photo",
			"settings" => $settings
		];
		
		if ($settings["min_width"] && $settings["min_height"]) {
			$field["subtitle"] = "(min ".$settings["min_width"]."x".$settings["min_height"].")";
		}
	} else {
		$field = [
			"id" => "*video",
			"type" => "video",
			"title" => "Video URL",
			"subtitle" => "(include http://)",
			"settings" => ["validation" => "link required"]
		];
	}
	
	// Matrix expects this to be encoded
	$field["settings"] = json_encode($field["settings"]);
	
	// Add our media field
	array_unshift($_POST["columns"], $field);
	
	// Ensure all of our columns are real
	$clean_columns = [];
	
	foreach ($_POST["columns"] as $column) {
		if ($column["id"]) {
			$column["id"] = "info][".$column["id"];
			$clean_columns[] = $column;
		}
	}
	
	$_POST["columns"] = $clean_columns;
	
	include SERVER_ROOT."core/admin/ajax/matrix-field.php";
