<?
	$perm = $admin->getResourceFolderPermission($_POST["folder"]);
	if ($perm != "p") {
		die("Permission denied.");
	}
	
	$folder = sqlescape($_POST["folder"]);
	$f = $_FILES["file"];
	if ($f["error"]) {
		if ($f["error"] == 2 || $f["error"] == 1) {
			$error = "File Too Large";
		} else {
			$error = "Upload Failed";
		}
	} else {
		$upload_service = new BigTreeUploadService;
		$temp_name = $f["tmp_name"];
		
		$n = strrev($f["name"]);
		$extension = strtolower(strrev(substr($n,0,strpos($n,"."))));
		
		list($iwidth,$iheight,$itype,$iattr) = getimagesize($temp_name);
		// It's a regular file
		if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
			$type = "file";
			$file = $upload_service->upload($temp_name,$f["name"],$options["directory"]);
			
			$admin->createResource($folder,$file,$f["name"],$extension);
		// It's an image
		} else {
			$type = "image";

			$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");
			$first_copy = $temp_name;
			
			// Let's crush this png.
			if ($itype == IMAGETYPE_PNG && $upload_service->optipng) {
				$first_copy = SITE_ROOT."files/".uniqid("temp-").".png";
				move_uploaded_file($temp_name,$first_copy);
				exec($upload_service->optipng." ".$first_copy);
			}
			
			// Let's crush the gif and see if we can make it a PNG.
			if ($itype == IMAGETYPE_GIF && $upload_service->optipng) {
				$first_copy = SITE_ROOT."files/".uniqid("temp-").".gif";
				move_uploaded_file($temp_name,$first_copy);
				
				exec($upload_service->optipng." ".$first_copy);
				if (file_exists(substr($first_copy,0,-3)."png")) {
					unlink($first_copy);
					$first_copy = substr($first_copy,0,-3)."png";
					$name_parts = BigTree::pathInfo($f["name"]);
					$name = $name_parts["filename"].".png";
				}
				
			}
			
			// Let's trim the jpg.
			if ($itype == IMAGETYPE_JPEG && $upload_service->jpegtran) {
				$first_copy = SITE_ROOT."files/".uniqid("temp-").".gif";
				move_uploaded_file($temp_name,$first_copy);
				
				exec($upload_service->jpegtran." -copy none -optimize -progressive $first_copy > $first_copy-trimmed");
				unlink($first_copy);
				$first_copy = $first_copy."-trimmed";
			}
			
			list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);
			
			// Now let's make the thumbnails we need for the image manager
			$thumbs = array();
			
			// First up is the list view
			$pinfo = BigTree::pathInfo($f["name"]);
			$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
			BigTree::createThumbnail($first_copy,$temp_thumb,100,100);
			
			list($twidth,$theight) = getimagesize($temp_thumb);
			$margin = floor((100 - $theight) / 2);
			
			$thumb = $upload_service->upload($temp_thumb,"list_thumb_".$pinfo["basename"],"files/resources/");
			$thumbs["bigtree_internal_list"] = $thumb;
			
			// Next up is the more info view
			$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
			BigTree::createThumbnail($first_copy,$temp_thumb,190,145);
			
			$thumb = $upload_service->upload($temp_thumb,"detail_thumb_".$pinfo["basename"],"files/resources/");
			$thumbs["bigtree_internal_detail"] = $thumb;
			
			// Go through all of the custom thumbs and do the magic.
			$more_thumb_types = json_decode($cms->getSetting("resource-thumbnail-sizes"),true);
			foreach ($more_thumb_types as $mtk => $mtt) {
				if ($iwidth > $mtt["width"] || $iheight > $mtt["height"]) {
					$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
					BigTree::createThumbnail($first_copy,$temp_thumb,$mtt["width"],$mtt["height"]);
				
					$thumb = $upload_service->upload($temp_thumb,$mtt["prefix"].$pinfo["basename"],"files/resources/");
					$thumbs[$mtk] = $thumb;
				}
			}
		
			// Upload the original to the proper place.
			$file = $upload_service->upload($first_copy,$f["name"],"files/resources/");
			
			$admin->createResource($folder,$file,$f["name"],$extension,"on",$iheight,$iwidth,$thumbs,$margin);
		}
	}
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" />
	</head>
	<body style="background: transparent;">
		<p class="file_browser_response">Successfully Uploaded</p>
		<script>
			parent.BigTreeFileManager.finishedUpload("<?=$file?>","<?=$type?>","<?=$iwidth?>","<?=$iheight?>");
		</script>
	</body>
</html>