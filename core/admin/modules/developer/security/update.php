<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$setting = new Setting("bigtree-internal-security-policy");
	$setting->Value = array(
		"user_fails" => array(
			"count" => $_POST["user_fails"]["count"] ? intval($_POST["user_fails"]["count"]) : "",
			"time" => $_POST["user_fails"]["time"] ? intval($_POST["user_fails"]["time"]) : "",
			"ban" => $_POST["user_fails"]["ban"] ? intval($_POST["user_fails"]["ban"]) : ""
		),
		"ip_fails" => array(
			"count" => $_POST["ip_fails"]["count"] ? intval($_POST["ip_fails"]["count"]) : "",
			"time" => $_POST["ip_fails"]["time"] ? intval($_POST["ip_fails"]["time"]) : "",
			"ban" => $_POST["ip_fails"]["ban"] ? intval($_POST["ip_fails"]["ban"]) : ""
		),
		"password" => array(
			"length" => $_POST["password"]["length"] ? intval($_POST["password"]["length"]) : "",
			"mixedcase" => $_POST["password"]["mixedcase"] ? "on" : "",
			"numbers" => $_POST["password"]["numbers"] ? "on" : "",
			"nonalphanumeric" => $_POST["password"]["nonalphanumeric"] ? "on" : ""
		),
		"suspect_geo_check" => $_POST["suspect_geo_check"] ? "on" : "",
		"include_daily_bans" => $_POST["include_daily_bans"] ? "on" : "",
		"allowed_ips" => $_POST["allowed_ips"],
		"banned_ips" => $_POST["banned_ips"],
		"remember_disabled" => $_POST["remember_disabled"] ? "on" : "",
		"two_factor" => $_POST["two_factor"]
	);
	$setting->save();

	Utils::growl("Security","Updated Policy");
	Router::redirect(DEVELOPER_ROOT);
	