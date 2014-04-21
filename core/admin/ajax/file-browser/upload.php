<?
	// If we're replacing an existing file, find out its name
	if (isset($_POST["replace"])) {
		$admin->requireLevel(1);
		$replacing = $admin->getResource($_POST["replace"]);
		$pinfo = BigTree::pathInfo($replacing["file"]);
		$replacing = $pinfo["basename"];
		// Set a recently replaced cookie so we don't use cached images
		setcookie('bigtree_admin[recently_replaced_file]',true,time()+300,str_replace(DOMAIN,"",WWW_ROOT));
	} else {
		$replacing = false;
	}

	$folder = isset($_POST["folder"]) ? sqlescape($_POST["folder"]) : false;
	$f = $_FILES["file"];
	$file_name = $replacing ? $replacing : $f["name"];

	// If the user doesn't have permission to upload to this folder, throw an error.
	$perm = $admin->getResourceFolderPermission($folder);
	if ($perm != "p") {
		$f["error"] = 9;
	}

	$error = false;
	// Check for file upload errors (or the permission error we faked above)
	if ($f["error"]) {
		if ($f["error"] == 2 || $f["error"] == 1) {
			$error = "The uploaded file was too large. (".BigTree::formatBytes(BigTree::uploadMaxFileSize())." max)";
		} elseif ($f["error"] == 9) {
			$error = "You do not have permission to upload to this folder.";
		} else {
			$error = "The upload failed (unknown error).";
		}
	// File successfully uploaded
	} elseif ($f["tmp_name"]) {
		$storage = new BigTreeStorage;
		$temp_name = $f["tmp_name"];

		// See if this file already exists
		if ($replacing || !$admin->matchResourceMD5($temp_name,$_POST["folder"])) {
			$md5 = md5_file($temp_name);

			// Get the name and file extension
			$n = strrev($file_name);
			$extension = strtolower(strrev(substr($n,0,strpos($n,"."))));

			// See if it's an image
			list($iwidth,$iheight,$itype,$iattr) = getimagesize($temp_name);

			// It's a regular file
			if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
				$type = "file";
				if ($replacing) {
					$file = $storage->replace($temp_name,$file_name,"files/resources/");
				} else {
					$file = $storage->store($temp_name,$file_name,"files/resources/");
				}
				// If we failed, either cloud storage upload failed, directory permissions are bad, or the file type isn't permitted
				if (!$file) {
					if ($storage->DisabledFileError) {
						$error = "The file you uploaded has a disallowed extension: $extension.";
					} else {
						$error = "The upload failed (unknown error).";
					}
				// Otherwise make the database entry for the file we uplaoded.
				} else {
					if (!$replacing) {
						$admin->createResource($folder,$file,$md5,$file_name,$extension);
					}
				}
			// It's an image
			} else {
				$type = "image";

				// We're going to create a list view and detail view thumbnail plus whatever we're requesting to have through Settings
				$thumbnails_to_create = array(
					"bigtree_internal_list" => array("width" => 100, "height" => 100, "prefix" => "bigtree_list_thumb_"),
					"bigtree_internal_detail" => array("width" => 190, "height" => 145, "prefix" => "bigtree_detail_thumb_")
				);
				$more_thumb_types = @json_decode($cms->getSetting("resource-thumbnail-sizes"),true);
				if (is_array($more_thumb_types)) {
					foreach ($more_thumb_types as $key => $thumb) {
						$thumbnails_to_create[$key] = $thumb;
					}
				}

				// Do lots of image awesomesauce.
				$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");
				$first_copy = $temp_name;

				// Let's crush this png.
				if ($itype == IMAGETYPE_PNG && $storage->optipng) {
					$first_copy = SITE_ROOT."files/".uniqid("temp-").".png";
					move_uploaded_file($temp_name,$first_copy);
					exec($storage->optipng." ".$first_copy);
				}

				// Let's crush the gif and see if we can make it a PNG.
				if ($itype == IMAGETYPE_GIF && $storage->optipng) {
					$first_copy = SITE_ROOT."files/".uniqid("temp-").".gif";
					move_uploaded_file($temp_name,$first_copy);

					exec($storage->optipng." ".$first_copy);
					if (file_exists(substr($first_copy,0,-3)."png")) {
						unlink($first_copy);
						$first_copy = substr($first_copy,0,-3)."png";
						$name_parts = BigTree::pathInfo($file_name);
						$name = $name_parts["filename"].".png";
					}

				}

				// Let's trim the jpg.
				if ($itype == IMAGETYPE_JPEG && $storage->jpegtran) {
					$first_copy = SITE_ROOT."files/".uniqid("temp-").".gif";
					move_uploaded_file($temp_name,$first_copy);

					exec($storage->jpegtran." -copy none -optimize -progressive $first_copy > $first_copy-trimmed");
					unlink($first_copy);
					$first_copy = $first_copy."-trimmed";
				}

				list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);

				foreach ($thumbnails_to_create as $thumb) {
					// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
					if (!$error) {
						$sizes = BigTree::getThumbnailSizes($first_copy,$thumb["width"],$thumb["height"]);
						if (!BigTree::imageManipulationMemoryAvailable($first_copy,$sizes[3],$sizes[4],$iwidth,$iheight)) {
							$error = "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.";
							unlink($first_copy);
						}
					}
				}

				if (!$error) {
					// Now let's make the thumbnails we need for the image manager
					$thumbs = array();
					$pinfo = BigTree::pathInfo($file_name);

					// Create a bunch of thumbnails
					foreach ($thumbnails_to_create as $key => $thumb) {
						if ($iwidth > $thumb["width"] || $iheight > $thumb["height"]) {
							$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
							BigTree::createThumbnail($first_copy,$temp_thumb,$thumb["width"],$thumb["height"]);

							if ($key == "bigtree_internal_list") {
								list($twidth,$theight) = getimagesize($temp_thumb);
								$margin = floor((100 - $theight) / 2);
							}

							if ($replacing) {
								$file = $storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],"files/resources/");
							} else {
								$file = $storage->store($temp_thumb,$thumb["prefix"].$pinfo["basename"],"files/resources/");
							}
							$thumbs[$key] = $file;
						}
					}

					// Upload the original to the proper place.
					if ($replacing) {
						$file = $storage->replace($first_copy,$file_name,"files/resources/");
					} else {
						$file = $storage->store($first_copy,$file_name,"files/resources/");
					}

					if (!$file) {
						$error = "The upload failed (unknown error).";
					} else {
						if (!$replacing) {
							$admin->createResource($folder,$file,$md5,$file_name,$extension,"on",$iheight,$iwidth,$thumbs,$margin);
						} else {
							$admin->updateResource($_POST["replace"],array(
								"date" => date("Y-m-d H:i:s"),
								"md5" => $md5,
								"height" => $iheight,
								"width" => $iwidth,
								"thumbs" => json_encode($thumbs),
								"list_thumb_margin" => $margin
							));
						}
					}
				}
			}
		}

		$storage = null;
	} else {
		$error = "The upload failed (unknown error).";
	}
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" />
	</head>
	<body style="background: transparent;">
		<script>
			<? if ($error) { ?>
			parent.BigTreeFileManager.uploadError("<?=htmlspecialchars($error)?>");
			<? } else { ?>
			parent.BigTreeFileManager.finishedUpload("<?=$file?>","<?=$type?>","<?=$iwidth?>","<?=$iheight?>");
			<? } ?>
		</script>
	</body>
</html>