<?
	$failed = false;
		
	// Let's check the minimum requirements for the image first before we store it anywhere.
	$image_info = getimagesize($temp_name);
	$iwidth = $image_info[0];
	$iheight = $image_info[1];
	$itype = $image_info[2];
	$channels = $image_info["channels"];

	// If the minimum height or width is not meant, do NOT let the image through.  Erase the change or update from the database.
	if ((isset($options["min_height"]) && $iheight < $options["min_height"]) || (isset($options["min_width"]) && $iwidth < $options["min_width"])) {
		$fails[] = array("field" => $options["title"], "error" => "Image uploaded did not meet the minimum size of ".$options["min_width"]."x".$options["min_height"]);
		$failed = true;
	}
	
	// If it's not a valid image, throw it out!
	if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
		$fails[] = array("field" => $options["title"], "error" =>  "An invalid file was uploaded. Valid file types: JPG, GIF, PNG.");
		$failed = true;
	}
	
	// See if it's CMYK
	if ($channels == 4) {
		$fails[] = array("field" => $options["title"], "error" =>  "A CMYK encoded file was uploaded. Please upload an RBG image.");
		$failed = true;
	}

	// Do EXIF Image Rotation
	$already_created_first_copy = false;
	if ($itype == IMAGETYPE_JPEG && function_exists("exif_read_data")) {
		$exif = exif_read_data($temp_name);
		$o = $exif['Orientation'];
		if ($o == 3 || $o == 6 || $o == 8) {
			$first_copy = $site_root."files/".uniqid("temp-").".jpg";
			$source = imagecreatefromjpeg($temp_name);
			
			if ($o == 3) {
				$source = imagerotate($source,180,0);
			} elseif ($o == 6) {
				$source = imagerotate($source,270,0);
			} else {
				$source = imagerotate($source,90,0);
			}
			
			imagejpeg($source,$first_copy);
			imagedestroy($source);
			$already_created_first_copy = true;
		}
	}
	
	$value = "";
	if (!$failed) {
		// Make a temporary copy to be used for thumbnails and crops.
		$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");
		
		if (!$already_created_first_copy) {
			$first_copy = $temp_name;
		}
		
		// Let's crush this png.
		if ($itype == IMAGETYPE_PNG && $upload_service->optipng) {
			$first_copy = $site_root."files/".uniqid("temp-").".png";
			move_uploaded_file($temp_name,$first_copy);
			
			exec($upload_service->optipng." ".$first_copy);
		}
		// Let's crush the gif and see if we can make it a PNG.
		if ($itype == IMAGETYPE_GIF && $upload_service->optipng) {
			$first_copy = $site_root."files/".uniqid("temp-").".gif";
			move_uploaded_file($temp_name,$first_copy);
			
			exec($upload_service->optipng." ".$first_copy);
			if (file_exists(substr($first_copy,0,-3)."png")) {
				unlink($first_copy);
				$first_copy = substr($first_copy,0,-3)."png";
				$name_parts = BigTree::pathInfo($name);
				$name = $name_parts["filename"].".png";
			}
			
		}
		// Let's trim the jpg.
		if (!$already_created_first_copy && $itype == IMAGETYPE_JPEG && $upload_service->jpegtran) {
			$first_copy = $site_root."files/".uniqid("temp-").".jpg";
			move_uploaded_file($temp_name,$first_copy);
			
			exec($upload_service->jpegtran." -copy none -optimize -progressive $first_copy > $first_copy-trimmed");
			unlink($first_copy);
			$first_copy = $first_copy."-trimmed";
		}
		
		list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);
		
		$temp_copy = $site_root."files/".uniqid("temp-").$itype_exts[$itype];
		BigTree::copyFile($first_copy,$temp_copy);
		
		// Upload the original to the proper place.
		$value = $upload_service->upload($first_copy,$name,$options["directory"]);
 		if (!$value) {
			$fails[] = array("field" => $options["title"], "error" => "Could not upload file.  The destination is not writable.");
			unlink($temp_copy);
			unlink($first_copy);
			$failed = true;
		}
	}
	
	// If we didn't fail, let's check on crops and thumbnails.
	if (!$failed) {
		$pinfo = BigTree::pathInfo($value);
		// Handle Crops
		foreach ($options["crops"] as $crop) {
			$cwidth = $crop["width"];
			$cheight = $crop["height"];
			
			// Check to make sure each dimension is greater then or equal to, but not both equal to the crop.
			if (($iheight >= $cheight && $iwidth > $cwidth) || ($iwidth >= $cwidth && $iheight > $cheight)) {
				$crops[] = array(
					"image" => $temp_copy,
					"directory" => $options["directory"],
					"name" => $pinfo["basename"],
					"width" => $cwidth,
					"height" => $cheight,
					"prefix" => $crop["prefix"],
					"thumbs" => $crop["thumbs"]
				);
			// If it's the same dimensions, let's see if they're looking for a prefix for whatever reason...
			} elseif ($iheight == $cheight && $iwidth == $cwidth) {
				if (is_array($crop["thumbs"])) {
					foreach ($crop["thumbs"] as $thumb) {
						$temp_thumb = $site_root."files/".uniqid("temp-").$itype_exts[$itype];
						BigTree::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"]);
						// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
						$upload_service->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$options["directory"]);
					}
				}

				$upload_service->upload($temp_copy,$crop["prefix"].$pinfo["basename"],$options["directory"],false);
			}
		}
		
		// Handle thumbnailing
		if (is_array($options["thumbs"])) {
			foreach ($options["thumbs"] as $thumb) {
				$temp_thumb = $site_root."files/".uniqid("temp-").$itype_exts[$itype];
				BigTree::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"]);
				// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
				$upload_service->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$options["directory"]);
			}
		}
	}
?>