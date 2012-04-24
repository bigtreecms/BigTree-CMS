<?
	$gateway = $cms->getSetting("bigtree-internal-payment-gateway");
	$gateway["service"] = "linkpoint";
	$gateway["settings"]["linkpoint-store"] = $_POST["linkpoint-store"];
	$gateway["settings"]["linkpoint-environment"] = $_POST["linkpoint-environment"];
	
	if ($_FILES["linkpoint-certificate"]["tmp_name"]) {
		$filename = BigTree::getAvailableFileName($server_root."custom/certificates/",$_FILES["linkpoint-certificate"]["name"]);
		BigTree::moveFile($_FILES["linkpoint-certificate"]["tmp_name"],$server_root."custom/certificates/".$filename);
		$gateway["settings"]["linkpoint-certificate"] = $filename;
	}
	
	$admin->updateSettingValue("bigtree-internal-payment-gateway",$gateway);
	
	$admin->growl("Developer","Updated Payment Gateway");
	header("Location: $developer_root");
	die();
?>