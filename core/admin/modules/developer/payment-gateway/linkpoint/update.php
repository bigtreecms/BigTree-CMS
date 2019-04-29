<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
	
	CSRF::verify();
	
	$data = Setting::value("bigtree-internal-payment-gateway");
	$data["service"] = "linkpoint";
	$data["settings"]["linkpoint-store"] = $_POST["linkpoint-store"];
	$data["settings"]["linkpoint-environment"] = $_POST["linkpoint-environment"];
	
	if ($_FILES["linkpoint-certificate"]["tmp_name"]) {
		$filename = FileSystem::getAvailableFileName(SERVER_ROOT."custom/certificates/", $_FILES["linkpoint-certificate"]["name"]);
		FileSystem::moveFile($_FILES["linkpoint-certificate"]["tmp_name"], SERVER_ROOT."custom/certificates/".$filename);
		$data["settings"]["linkpoint-certificate"] = $filename;
	}
	
	Setting::updateValue("bigtree-internal-payment-gateway", $data, true);
	Utils::growl("Developer", "Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	