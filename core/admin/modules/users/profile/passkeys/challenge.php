<?php
	/**
	 * Returns WebAuthn registration options as JSON (requires login).
	 * Called by the passkeys management page via fetch().
	 */

	ob_clean();
	header("Content-Type: application/json");

	if (!BigTreeAdmin::passkeysEnabled()) {
		http_response_code(501);
		echo json_encode(["error" => "Passkeys are not available on this server."]);
		die();
	}

	$passkeys = BigTreeAdmin::getUserPasskeys($admin->ID);
	$existing_ids = array_column($passkeys, "credential_id");

	$parsed = parse_url(ADMIN_ROOT);
	$rp_id  = $parsed["host"];
	$rp_name = !empty($site["nav_title"]) ? $site["nav_title"] : $rp_id;

	$user_info = $admin->getUser($admin->ID);
	$options = BigTree\WebAuthn::getRegistrationOptions(
		$rp_id,
		$rp_name,
		$admin->ID,
		$user_info["email"],
		$user_info["name"] ?: $user_info["email"],
		$existing_ids
	);

	echo json_encode($options);
	die();
