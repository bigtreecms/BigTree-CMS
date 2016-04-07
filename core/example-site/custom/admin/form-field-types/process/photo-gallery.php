<?php
	namespace BigTree;

	// Modified Photo Gallery to add Attribution and Link attributes
	$photo_gallery = array();
	if (is_array($field["input"])) {
		foreach ($field["input"] as $photo_count => $data) {
			// Existing Data
			if ($data["image"]) {
				$data["caption"] = Text::htmlEncode($data["caption"]);
				$data["attribution"] = Text::htmlEncode($data["attribution"]);
				$data["link"] = Text::htmlEncode($data["link"]);
				$photo_gallery[] = $data;
			// Uploaded File
			} elseif ($field["file_input"][$photo_count]["image"]["name"]) {
				$field_copy = $field;
				$field_copy["file_input"] = $field["file_input"][$photo_count]["image"];

				$file = $admin->processImageUpload($field_copy);
				if ($file) {
					$photo_gallery[] = array("image" => $file,"caption" => Text::htmlEncode($data["caption"]),"attribution" => Text::htmlEncode($data["attribution"]),"link" => Text::htmlEncode($data["link"]));
				}
			// File From Image Manager
			} elseif ($data["existing"]) {
				$data["existing"] = str_replace(WWW_ROOT,SITE_ROOT,$data["existing"]);
				$pinfo = pathinfo($data["existing"]);

				$field_copy = $field;
				$field_copy["file_input"] = array("name" => $pinfo["basename"],"tmp_name" => SITE_ROOT."files/".uniqid("temp-").".img","error" => false);
				FileSystem::copyFile($data["existing"],$field_copy["file_input"]["tmp_name"]);

				$file = $admin->processImageUpload($field_copy);
				if ($file) {
					$photo_gallery[] = array("image" => $file,"caption" => Text::htmlEncode($data["caption"]),"attribution" => Text::htmlEncode($data["attribution"]),"link" => Text::htmlEncode($data["link"]));
				}
			}
		}
	}

	$field["output"] = $photo_gallery;