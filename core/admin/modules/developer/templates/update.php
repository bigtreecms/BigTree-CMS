<?
	BigTree::globalizePOSTVars();

	$template = $cms->getTemplate($id);

	if ($_FILES["image"]["tmp_name"]) {
		$image = BigTree::getAvailableFileName(SERVER_ROOT."custom/admin/images/templates/",$_FILES["image"]["name"]);
		move_uploaded_file($_FILES["image"]["tmp_name"],SERVER_ROOT."custom/admin/images/templates/".$image);
		chmod(SERVER_ROOT."custom/admin/images/templates/".$image,0777);
		$image = mysql_real_escape_string($image);
	} elseif ($existing_image) {
		$image = $existing_image;
	} else {
		$image = $template["image"];
	}	
	
	$admin->updateTemplate($id,$name,$description,$level,$module,$image,$callouts_enabled,$resources);
	
	$admin->growl("Developer","Updated Template");
	header("Location: ".$developer_root."templates/view/");
	die();
?>