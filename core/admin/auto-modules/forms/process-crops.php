<?
	error_reporting(E_ALL);
	ini_set("display_errors","on");
	
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	
	// Calculate the redirect location.
	$pieces = explode("-",$action["route"]);
	if (count($pieces) == 2) {
		$redloc = ADMIN_ROOT.$module["route"]."/view-".$pieces[1]."/";
	} else {
		$redloc = ADMIN_ROOT.$module["route"]."/";
	}
	
	$crops = json_decode($_POST["crop_info"],true);
	$after = array();
	
	foreach ($crops as $key => $crop) {
		$image_src = $crop["image"];
		$cwidth = $crop["width"];
		$cheight = $crop["height"];
		$prefix = $crop["prefix"];
		$x = $_POST["x"][$key];
		$y = $_POST["y"][$key];
		$width = $_POST["width"][$key];
		$height = $_POST["height"][$key];
		$thumbs = $crop["thumbs"];
		$retina = $crop["retina"];
		
		// If we're replacing the actual image, do it last.
		if (!$prefix) {
			$after[] = $crop;
		// Otherwise, let's make the crop.
		} else {
			$pinfo = pathinfo($image_src);
			
			$temp_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
			BigTree::createCrop($image_src,$temp_crop,$x,$y,$cwidth,$cheight,$width,$height,$retina);
			foreach ($thumbs as $thumb) {
				$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
				BigTree::createThumbnail($temp_crop,$temp_thumb,$thumb["width"],$thumb["height"],$retina);
				$upload_service->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
			}
			$upload_service->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
		}
	}
	
	foreach ($after as $crop) {
		$pinfo = pathinfo($crop["image"]);
		
		$temp_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
		BigTree::createCrop($crop["image"],$temp_crop,$x,$y,$cwidth,$cheight,$width,$height,$crop["retina"]);

		foreach ($thumbs as $thumb) {
			$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
			BigTree::createThumbnail($temp_crop,$temp_thumb,$thumb["width"],$thumb["height"],$crop["retina"]);
			$upload_service->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
		}
		
		$upload_service->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
	}
	
	foreach ($crops as $crop) {
		unlink($crop["image"]);
	}
	
	die();
	
	BigTree::redirect($redloc);
?>