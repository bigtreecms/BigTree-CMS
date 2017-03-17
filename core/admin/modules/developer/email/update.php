<?
	$admin->verifyCSRFToken();
	$settings = $cms->getSetting("bigtree-internal-email-service");

	if ($_POST["service"] == "mandrill") {
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

	$admin->updateSettingValue("bigtree-internal-email-service",$settings);
	$admin->growl("Developer","Updated Email Service");
	BigTree::redirect(DEVELOPER_ROOT);
?>
