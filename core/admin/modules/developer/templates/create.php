<?
	BigTree::globalizePOSTVars();
	
	// Let's see if the ID has already been used.
	if ($cms->getTemplate($id)) {
		$_SESSION["bigtree_admin"]["admin_saved"] = $_POST;
		$_SESSION["bigtree_admin"]["admin_error"] = true;
		BigTree::redirect($developer_root."templates/add/");
	}
	
	if ($_FILES["image"]["tmp_name"]) {
		$image = BigTree::getAvailableFileName(SERVER_ROOT."custom/admin/images/templates/",$_FILES["image"]["name"]);
		move_uploaded_file($_FILES["image"]["tmp_name"],SERVER_ROOT."custom/admin/images/templates/".$image);
		chmod(SERVER_ROOT."custom/admin/images/templates/".$image,0777);
		$image = sqlescape($image);
	} elseif ($existing_image) {
		$image = $existing_image;
	} else {
		$image = "page.png";
	}
	
	$admin->createTemplate($id,$name,$description,$routed,$level,$module,$image,$callouts_enabled,$resources);	
	
	$admin->growl("Developer","Created Template");
	BigTree::redirect($developer_root."templates/");
?>