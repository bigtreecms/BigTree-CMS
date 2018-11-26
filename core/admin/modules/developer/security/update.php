<?php
	$admin->verifyCSRFToken();

	BigTree::globalizePOSTVars();
	
	$admin->updateInternalSettingValue("bigtree-internal-security-policy", array(
		"user_fails" => array(
			"count" => $user_fails["count"] ? intval($user_fails["count"]) : "",
			"time" => $user_fails["time"] ? intval($user_fails["time"]) : "",
			"ban" => $user_fails["ban"] ? intval($user_fails["ban"]) : ""
		),
		"ip_fails" => array(
			"count" => $ip_fails["count"] ? intval($ip_fails["count"]) : "",
			"time" => $ip_fails["time"] ? intval($ip_fails["time"]) : "",
			"ban" => $ip_fails["ban"] ? intval($ip_fails["ban"]) : ""
		),
		"password" => array(
			"invitations" => $password["invitations"] ? "on" : "",
			"length" => $password["length"] ? intval($password["length"]) : "",
			"mixedcase" => $password["mixedcase"] ? "on" : "",
			"numbers" => $password["numbers"] ? "on" : "",
			"nonalphanumeric" => $password["nonalphanumeric"] ? "on" : ""
		),
		"suspect_geo_check" => $suspect_geo_check ? "on" : "",
		"include_daily_bans" => $include_daily_bans ? "on" : "",
		"allowed_ips" => $allowed_ips,
		"banned_ips" => $banned_ips,
		"remember_disabled" => $remember_disabled ? "on" : "",
		"two_factor" => $two_factor,
		"logout_all" => $logout_all ? "on" : ""
	));
	
	$admin->growl("Security","Updated Policy");
	BigTree::redirect(DEVELOPER_ROOT);
