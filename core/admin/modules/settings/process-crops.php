<?	
	$admin->processCrops(json_decode($_POST["crop_info"],true));
	BigTree::redirect(ADMIN_ROOT."settings/");
?>