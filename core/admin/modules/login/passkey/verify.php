<?php
	/**
	 * Verifies a WebAuthn assertion POST and establishes an admin session.
	 * Expects JSON body: { id, clientDataJSON, authenticatorData, signature, stay_logged_in }
	 * Returns JSON: { success: true, redirect: "url" } or { success: false, error: "..." }
	 */

	$body = json_decode(file_get_contents("php://input"), true);

	ob_clean();
	header("Content-Type: application/json");

	if (!BigTreeAdmin::passkeysEnabled()) {
		http_response_code(501);
		echo json_encode(["success" => false, "error" => "Passkeys are not available on this server."]);
		die();
	}

	if (empty($body["id"]) || empty($body["clientDataJSON"]) || empty($body["authenticatorData"]) || empty($body["signature"])) {
		echo json_encode(["success" => false, "error" => "Missing required fields"]);
		die();
	}

	$stay_logged_in = !empty($body["stay_logged_in"]);

	$redirect = BigTreeAdmin::loginPasskey(
		$body["id"],
		$body["clientDataJSON"],
		$body["authenticatorData"],
		$body["signature"],
		$stay_logged_in
	);

	if ($redirect) {
		echo json_encode(["success" => true, "redirect" => $redirect]);
	} else {
		echo json_encode(["success" => false, "error" => "Authentication failed. The passkey could not be verified."]);
	}

	die();
