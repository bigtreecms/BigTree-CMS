<?php
	$admin->verifyCSRFToken();
	$settings = $cms->getSetting("bigtree-internal-email-service");

	if ($_POST["service"] == "smtp") {
		$settings["settings"]["smtp_host"] = $_POST["smtp_host"];
		$settings["settings"]["smtp_port"] = $_POST["smtp_port"];
		$settings["settings"]["smtp_security"] = $_POST["smtp_security"];
		$settings["settings"]["smtp_user"] = $_POST["smtp_user"];
		$settings["settings"]["smtp_password"] = $_POST["smtp_password"];
	} elseif ($_POST["service"] == "mandrill") {
		$settings["settings"]["mandrill_key"] = $_POST["mandrill_key"];
	} elseif ($_POST["service"] == "mailgun") {
		$settings["settings"]["mailgun_key"] = $_POST["mailgun_key"];
		$settings["settings"]["mailgun_domain"] = $_POST["mailgun_domain"];
	} elseif ($_POST["service"] == "postmark") {
		$settings["settings"]["postmark_key"] = $_POST["postmark_key"];
	} elseif ($_POST["service"] == "sendgrid") {
		$settings["settings"]["sendgrid_api_user"] = $_POST["sendgrid_api_user"];
		$settings["settings"]["sendgrid_api_key"] = $_POST["sendgrid_api_key"];
	}
	$settings["service"] = $_POST["service"];
	$settings["settings"]["bigtree_from"] = $_POST["bigtree_from"];

	$admin->updateInternalSettingValue("bigtree-internal-email-service", $settings);
	$admin->growl("Developer","Updated Email Service");
	BigTree::redirect(DEVELOPER_ROOT);
