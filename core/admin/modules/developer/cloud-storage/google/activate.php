<?
	$admin->verifyCSRFToken();
	
	$cloud->Settings["key"] = trim($_POST["key"]);
	$cloud->Settings["secret"] = trim($_POST["secret"]);
	$cloud->Settings["project"] = trim($_POST["project"]);
	$cloud->Settings["certificate_email"] = trim($_POST["certificate_email"]);
	if ($_FILES["private_key"]["tmp_name"]) {
		move_uploaded_file($_FILES["private_key"]["tmp_name"],SERVER_ROOT."custom/google-cloud-private-key.p12");
		$cloud->Settings["private_key"] = SERVER_ROOT."custom/google-cloud-private-key.p12";
	}
	$cloud->oAuthRedirect();
?>