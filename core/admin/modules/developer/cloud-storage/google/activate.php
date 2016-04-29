<?php
	$google->Key = trim($_POST["key"]);
	$google->Secret = trim($_POST["secret"]);
	$google->Project = trim($_POST["project"]);
	$google->CertificateEmail = trim($_POST["certificate_email"]);
	
	if ($_FILES["private_key"]["tmp_name"]) {
		move_uploaded_file($_FILES["private_key"]["tmp_name"],SERVER_ROOT."custom/google-cloud-private-key.p12");

		$google->PrivateKey = SERVER_ROOT."custom/google-cloud-private-key.p12";
	}

	$google->Setting->save();
	
	$google->oAuthRedirect();
	