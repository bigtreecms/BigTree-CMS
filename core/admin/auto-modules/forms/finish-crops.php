<?php
	namespace BigTree;
	
	/**
	 * @global ModuleForm $form
	 */
	
	// Clean up
	$crops = Cache::get("org.bigtreecms.crops", $_SESSION["bigtree_admin"]["form_data"]["crop_key"]);
	
	foreach ($crops as $crop) {
		@unlink($crop["image"]);
	}
	
	Cache::delete("org.bigtreecms.crops", $_SESSION["bigtree_admin"]["form_data"]["crop_key"]);
	
	if (count($_SESSION["bigtree_admin"]["form_data"]["errors"])) {
		Router::redirect($form->Root."error/");
	} else {
		// We set this session and destroy the other so that if someone clicks back after cropping we can redirect them to the page prior to the crop.
		$_SESSION["bigtree_admin"]["cropper_previous_page"] = $_SESSION["bigtree_admin"]["form_data"]["edit_link"];
		
		$redirect_url = $_SESSION["bigtree_admin"]["form_data"]["return_link"];
		unset($_SESSION["bigtree_admin"]["form_data"]);
		
		Router::redirect($redirect_url);
	}
