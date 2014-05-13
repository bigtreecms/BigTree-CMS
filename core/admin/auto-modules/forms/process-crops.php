<?	
	$admin->processCrops(json_decode($_POST["crop_info"],true));

	if (count($_SESSION["bigtree_admin"]["form_data"]["errors"])) {
		BigTree::redirect($bigtree["form_root"]."error/");
	} else {
		// We set this session and destroy the other so that if someone clicks back after cropping we can redirect them to the page prior to the crop.
		$_SESSION["bigtree_admin"]["cropper_previous_page"] = $_SESSION["bigtree_admin"]["form_data"]["edit_link"];
		unset($_SESSION["bigtree_admin"]["form_data"]);
		BigTree::redirect($_POST["return_page"]);
	}
?>