<?
	$storage = new BigTreeStorage;

	// We're going to loop through captions and get the images for them if we have that.
	if (is_array($field["input"])) {
		$photo_gallery = array();
		foreach ($field["input"] as $photo_count => $data) {
			// If we have image data, it's a previously uploaded image we haven't changed, so just add it back to the photo gallery array.
			if ($data["image"]) {
				$data["caption"] = htmlspecialchars(htmlspecialchars_decode($data["caption"]));
				$photo_gallery[] = $data;
			// Otherwise, let's see if we have file information in the files array.
			} elseif ($field["file_input"][$photo_count]["image"]["name"]) {
				$name = $field["file_input"][$photo_count]["image"]["name"];
				$tmp_name = $field["file_input"][$photo_count]["image"]["tmp_name"];
				$error = $field["file_input"][$photo_count]["image"]["error"];

				if ($error == 1 || $error == 2) {
					$fails[] = array("field" => $field["options"]["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
				} elseif ($error == 3) {
					$fails[] = array("field" => $field["options"]["title"], "error" => "The file upload failed ($name).");
				} else {
					include BigTree::path("admin/form-field-types/process/_photo-process.php");
					if (!$failed) {
						$photo_gallery[] = array("caption" => htmlspecialchars(htmlspecialchars_decode($data["caption"])),"image" => $field["output"]);
					}
				}
			} elseif ($data["existing"]) {
				$data["existing"] = str_replace(WWW_ROOT,SITE_ROOT,$data["existing"]);
				$pinfo = BigTree::pathInfo($data["existing"]);
				
				// We're going to need to create a local copy if we need more 
				if ((is_array($field["options"]["crops"]) && count($field["options"]["crops"])) || (is_array($field["options"]["thumbs"]) && count($field["options"]["thumbs"]))) {
					$local_copy = SITE_ROOT."files/".uniqid("temp-").$pinfo["extension"];
					file_put_contents($local_copy,file_get_contents($data["existing"]));
					
					$file_name = $storage->upload($local_copy,$pinfo["basename"],$field["options"]["directory"],false);
					$pinfo = BigTree::pathInfo($file_name);
				
					if (is_array($field["options"]["crops"])) {
						foreach ($field["options"]["crops"] as $crop) {
							// Make a square if the user forgot to enter one of the crop dimensions.
							if (!$crop["height"]) {
								$crop["height"] = $crop["width"];
							} elseif (!$crop["width"]) {
								$crop["width"] = $crop["height"];
							}
							$bigtree["crops"][] = array(
								"image" => $local_copy,
								"directory" => $field["options"]["directory"],
								"retina" => $field["options"]["retina"],
								"name" => $pinfo["basename"],
								"width" => $crop["width"],
								"height" => $crop["height"],
								"prefix" => $crop["prefix"],
								"thumbs" => $crop["thumbs"]
							);
						}
					}
					
					if (is_array($field["options"]["thumbs"])) {
						foreach ($field["options"]["thumbs"] as $thumb) {
							$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
							BigTree::createThumbnail($local_copy,$temp_thumb,$thumb["width"],$thumb["height"],$field["options"]["retina"],$field["options"]["grayscale"]);
							// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
							$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$field["options"]["directory"]);
						}
					}
					
					$photo_gallery[] = array("caption" => htmlspecialchars(htmlspecialchars_decode($data["caption"])), "image" => $file_name);
				// If we don't have any crops or thumbnails we don't need to change the location of the file, so just use the existing one.
				} else {
					$photo_gallery[] = array("caption" => htmlspecialchars(htmlspecialchars_decode($data["caption"])), "image" => str_replace(SITE_ROOT,STATIC_ROOT,$data["existing"]));
				}
			}
		}
		
		$bigtree["output"] = $photo_gallery;
	// If the input is a string, it's probably from a callout that was never edited.
	} elseif (is_string($field["input"])) {
		$field["output"] = json_decode($field["input"],true);
	}
?>