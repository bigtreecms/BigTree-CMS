<?
	BigTree::globalizePOSTVars();
	
	// Let's see if the ID has already been used.
	if ($cms->getTemplate($id)) {
		$_SESSION["bigtree"]["admin_saved"] = $_POST;
		$_SESSION["bigtree"]["admin_error"] = true;
		header("Location: ../add/");
		die();
	}
	
	if ($_FILES["image"]["tmp_name"]) {
	    $image = BigTree::getAvailableFileName($GLOBALS["server_root"]."custom/admin/images/templates/",$_FILES["image"]["name"]);
	    move_uploaded_file($_FILES["image"]["tmp_name"],$GLOBALS["server_root"]."custom/admin/images/templates/".$image);
	    chmod($GLOBALS["server_root"]."custom/admin/images/templates/".$image,0777);
	    $image = mysql_real_escape_string($image);
	} elseif ($existing_image) {
	    $image = $existing_image;
	} else {
	    $image = "page.png";
	}
	
	$admin->createTemplate($id,$name,$description,$routed,$level,$module,$image,$callouts_enabled,$resources);	
	
	$admin->growl("Developer","Created Template");
	header("Location: ".$developer_root."templates/view/");
	die();
?>