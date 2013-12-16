<?
	$cloud->Settings["key"] = $_POST["key"];
	$cloud->Settings["secret"] = $_POST["secret"];
	$cloud->Settings["project"] = $_POST["project"];
	$cloud->Settings["certificate_email"] = $_POST["certificate_email"];
	if ($_FILES["private_key"]["tmp_name"]) {
		move_uploaded_file($_FILES["private_key"]["tmp_name"],SERVER_ROOT."custom/google-cloud-private-key.p12");
		$cloud->Settings["private_key"] = SERVER_ROOT."custom/google-cloud-private-key.p12";
	}
	$cloud->oAuthRedirect();
?>