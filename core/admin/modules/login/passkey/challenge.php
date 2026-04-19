<?php
	/**
	 * Returns a WebAuthn authentication challenge as JSON.
	 * Accessible without being logged in (under the login/ route).
	 * Empty allowCredentials causes the browser to show all discoverable credentials.
	 */

	ob_clean();
	header("Content-Type: application/json");

	if (!BigTreeAdmin::passkeysEnabled()) {
		http_response_code(501);
		echo json_encode(["error" => "Passkeys are not available on this server."]);
		die();
	}

	$parsed = parse_url(ADMIN_ROOT);
	$rp_id  = $parsed["host"];
	$options = BigTree\WebAuthn::getAuthenticationOptions($rp_id, []);

	echo json_encode($options);
	die();
