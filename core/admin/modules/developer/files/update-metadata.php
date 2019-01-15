<?php
	$metadata = [
		"file" => [],
		"image" => [],
		"video" => []
	];

	foreach ($_POST as $key => $data) {
		if ($key == "file" || $key == "image" || $key == "video") {
			foreach ($data["ids"] as $id_key => $id) {
				$metadata[$key][$id] = [
					"id" => BigTree::safeEncode($id),
					"title" => BigTree::safeEncode($data["titles"][$id_key]),
					"subtitle" => BigTree::safeEncode($data["subtitles"][$id_key]),
					"type" => $data["types"][$id_key],
					"settings" => json_decode(str_replace(["\r", "\n"], ['\r','\n'], $data["settings"][$id_key]), true)
				];				
			}
		}
	}

	BigTreeJSONDB::update("config", "file-metadata", [
		"file" => $metadata["file"],
		"image" => $metadata["image"],
		"video" => $metadata["video"]
	]);
	
	$admin->growl("File Metadata", "Updated Fields");

	BigTree::redirect(DEVELOPER_ROOT."files/");
