<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	
	// Calculate the redirect location.
	$pieces = explode("-",$action["route"]);
	if (count($pieces) == 2) {
		$redloc = $admin_root.$module["route"]."/view-".$pieces[1]."/";
	} else {
		$redloc = $admin_root.$module["route"]."/";
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
		
		// If we're replacing the actual image, do it last.
		if (!$prefix) {
			$after[] = $crop;
		// Otherwise, let's make the crop.
		} else {
			$pinfo = pathinfo($image_src);
			
			$temp_crop = $site_root."files/".uniqid("temp-").".".$pinfo["extension"];
			BigTree::createCrop($image_src,$temp_crop,$x,$y,$cwidth,$cheight,$width,$height);
			foreach ($thumbs as $thumb) {
				$temp_thumb = $site_root."files/".uniqid("temp-").".".$pinfo["extension"];
				BigTree::createThumbnail($temp_crop,$temp_thumb,$thumb["width"],$thumb["height"]);
				$upload_service->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
			}
			$upload_service->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
		}
	}
	
	foreach ($after as $crop) {
		$pinfo = pathinfo($crop["image"]);
		
		$temp_crop = $site_root."files/".uniqid("temp-").".".$pinfo["extension"];
		BigTree::createCrop($crop["image"],$temp_crop,$x,$y,$cwidth,$cheight,$width,$height);

		foreach ($thumbs as $thumb) {
			$temp_thumb = $site_root."files/".uniqid("temp-").".".$pinfo["extension"];
			BigTree::createThumbnail($temp_crop,$temp_thumb,$thumb["width"],$thumb["height"]);
			$upload_service->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
		}
		
		$upload_service->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
	}
	
	foreach ($crops as $crop) {
		unlink($crop["image"]);
	}
	
	header("Location: $redloc");
	die();
?>