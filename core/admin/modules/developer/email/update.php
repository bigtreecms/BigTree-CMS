<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$data = [];

	if ($_POST["service"] == "smtp") {
		$data["settings"]["smtp_host"] = $_POST["smtp_host"];
		$data["settings"]["smtp_port"] = $_POST["smtp_port"];
		$data["settings"]["smtp_security"] = $_POST["smtp_security"];
		$data["settings"]["smtp_user"] = $_POST["smtp_user"];
		$data["settings"]["smtp_password"] = $_POST["smtp_password"];		
	} elseif ($_POST["service"] == "mandrill") {
		$data["settings"]["mandrill_key"] = $_POST["mandrill_key"];
	} elseif ($_POST["service"] == "mailgun") {
		$data["settings"]["mailgun_key"] = $_POST["mailgun_key"];
		$data["settings"]["mailgun_domain"] = $_POST["mailgun_domain"];
	} elseif ($_POST["service"] == "postmark") {
		$data["settings"]["postmark_key"] = $_POST["postmark_key"];
	} elseif ($_POST["service"] == "sendgrid") {
		$data["settings"]["sendgrid_api_user"] = $_POST["sendgrid_api_user"];
		$data["settings"]["sendgrid_api_key"] = $_POST["sendgrid_api_key"];
	}
	
	$data["service"] = $_POST["service"];
	$data["settings"]["bigtree_from"] = $_POST["bigtree_from"];
	$data["settings"]["bigtree_from_name"] = $_POST["bigtree_from_name"];
	
	Setting::updateValue("bigtree-internal-email-service", $data, true);
	Utils::growl("Developer","Updated Email Service");
	Router::redirect(DEVELOPER_ROOT);
	