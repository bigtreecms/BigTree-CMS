<?php
	namespace BigTree;
	
	$metadata = [
		"file" => [],
		"image" => [],
		"video" => []
	];

	foreach ($_POST as $key => $data) {
		if ($key == "file" || $key == "image" || $key == "video") {
			foreach ($data["ids"] as $id_key => $id) {
				$metadata[$key][$id] = [
					"id" => Text::htmlEncode($id),
					"title" => Text::htmlEncode($data["titles"][$id_key]),
					"subtitle" => Text::htmlEncode($data["subtitles"][$id_key]),
					"type" => $data["types"][$id_key],
					"settings" => json_decode(str_replace(["\r", "\n"], ['\r','\n'], $data["settings"][$id_key]), true)
				];				
			}
		}
	}

	DB::update("config", "file-metadata", [
		"file" => $metadata["file"],
		"image" => $metadata["image"],
		"video" => $metadata["video"]
	]);
	
	Admin::growl("File Metadata", "Updated Fields");
	Router::redirect(DEVELOPER_ROOT."files/");
