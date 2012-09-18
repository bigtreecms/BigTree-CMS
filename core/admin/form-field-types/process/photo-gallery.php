<?
	if (is_array($data[$key])) {
		$photo_gallery = array();
		foreach ($data[$key] as $pcount => $d) {
			if ($d["image"]) {
				$d["caption"] = htmlspecialchars($d["caption"]);
				$photo_gallery[] = $d;
			} elseif ($file_data[$key]["name"][$pcount]["image"] || $file_data["name"][$key][$pcount]["image"] || (is_array($file_data["image"]) && is_array($file_data["image"]["name"]) && is_array($file_data["image"]["name"][$pcount]) && $file_data["image"]["name"][$pcount]["image"])) {
				// Uploaded a new photo.
				
				if ($file_data[$key]["name"][$pcount]["image"]) {
					$temp_name = $file_data[$key]["tmp_name"][$pcount]["image"];
					$name = $file_data[$key]["name"][$pcount]["image"];
					$error = $file_data[$key]["error"][$pcount]["image"];
				} elseif ($file_data["name"][$key][$pcount]["image"]) {
					$temp_name = $file_data["tmp_name"][$key][$pcount]["image"];
					$name = $file_data["name"][$key][$pcount]["image"];
					$error = $file_data["error"][$key][$pcount]["image"];
				} else {
					$temp_name = $file_data["image"]["tmp_name"][$pcount]["image"];
					$name = $file_data["image"]["name"][$pcount]["image"];
					$error = $file_data["image"]["error"][$pcount]["image"];
				}
				
				if ($error == 1 || $error == 2) {
					$fails[] = array("field" => $options["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
				} elseif ($error == 3) {
					$fails[] = array("field" => $options["title"], "error" => "The file upload failed ($name).");
				} else {
					include BigTree::path("admin/form-field-types/process/_photo-process.php");
				
					if (!$failed) {
						$photo_gallery[] = array("caption" => htmlspecialchars($d["caption"]),"image" => $value);
					}
				}
			} elseif ($d["existing"]) {
				$d["existing"] = str_replace(WWW_ROOT,SITE_ROOT,$d["existing"]);
				$pinfo = BigTree::pathInfo($d["existing"]);
				
				// We're going to need to create a local copy if we need more 
				if ((is_array($options["crops"]) && count($options["crops"])) || (is_array($options["thumbs"]) && count($options["thumbs"]))) {
					$local_copy = SITE_ROOT."files/".uniqid("temp-").$pinfo["extension"];
					file_put_contents($local_copy,file_get_contents($d["existing"]));
					
					$value = $upload_service->upload($local_copy,$pinfo["basename"],$options["directory"],false);
					$pinfo = BigTree::pathInfo($value);
				
					if (is_array($options["crops"])) {
						foreach ($options["crops"] as $crop) {
							// Make a square if the user forgot to enter one of the crop dimensions.
							if (!$crop["height"]) {
								$crop["height"] = $crop["width"];
							} elseif (!$crop["width"]) {
								$crop["width"] = $crop["height"];
							}
							$crops[] = array(
								"image" => $local_copy,
								"directory" => $options["directory"],
								"retina" => $options["retina"],
								"name" => $pinfo["basename"],
								"width" => $crop["width"],
								"height" => $crop["height"],
								"prefix" => $crop["prefix"],
								"thumbs" => $crop["thumbs"]
							);
						}
					}
					
					if (is_array($options["thumbs"])) {
						foreach ($options["thumbs"] as $thumb) {
							$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
							BigTree::createThumbnail($local_copy,$temp_thumb,$thumb["width"],$thumb["height"],$options["retina"],$options["grayscale"]);
							// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
							$upload_service->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$options["directory"]);
						}
					}
					
					$photo_gallery[] = array("caption" => htmlspecialchars($d["caption"]), "image" => $value);
				// If we don't have any crops or thumbnails we don't need to change the location of the file, so just use the existing one.
				} else {
					$photo_gallery[] = array("caption" => htmlspecialchars($d["caption"]), "image" => $r["file"]);
				}
			}
		}
		
		$value = json_encode(BigTree::translateArray($photo_gallery));
	} else {
		$value = $data[$key];
	}
?>