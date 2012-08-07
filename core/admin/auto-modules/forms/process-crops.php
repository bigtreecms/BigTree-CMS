<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	
	$crops = json_decode($_POST["crop_info"],true);
	
	foreach ($crops as $key => $crop) {
		$image_src = $crop["image"];
		$target_width = $crop["width"];
		$target_height = $crop["height"];
		$prefix = $crop["prefix"];
		$x = $_POST["x"][$key];
		$y = $_POST["y"][$key];
		$width = $_POST["width"][$key];
		$height = $_POST["height"][$key];
		$thumbs = $crop["thumbs"];
		
		$pinfo = pathinfo($image_src);
			
		$temp_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
		BigTree::createCrop($image_src,$temp_crop,$x,$y,$target_width,$target_height,$width,$height,$crop["retina"],$crop["grayscale"]);
		foreach ($thumbs as $thumb) {
		    // We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
		    list($type,$w,$h,$result_width,$result_height) = BigTree::getThumbnailSizes($temp_crop,$thumb["width"],$thumb["height"]);
		    
		    $temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
		    BigTree::createCrop($image_src,$temp_thumb,$x,$y,$result_width,$result_height,$width,$height,$crop["retina"],$thumb["grayscale"]);
		    $upload_service->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
		}
		$upload_service->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
	}
	
	// Remove all the temporary images
	foreach ($crops as $crop) {
		@unlink($crop["image"]);
	}
	
	BigTree::redirect($_POST["return_page"]);
?>