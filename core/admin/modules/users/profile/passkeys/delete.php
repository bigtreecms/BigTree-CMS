<?php
	/**
	 * Deletes a passkey owned by the current user.
	 * Expects JSON body: { id }
	 * Returns JSON: { success: true } or { success: false, error: "..." }
	 */

	$body = json_decode(file_get_contents("php://input"), true);

	ob_clean();
	header("Content-Type: application/json");

	if (!BigTreeAdmin::passkeysEnabled()) {
		http_response_code(501);
		echo json_encode(["success" => false, "error" => "Passkeys are not available on this server."]);
		die();
	}

	$id = intval($body["id"] ?? 0);
	if (!$id) {
		echo json_encode(["success" => false, "error" => "Invalid passkey ID"]);
		die();
	}

	BigTreeAdmin::deletePasskey($id, $admin->ID);
	echo json_encode(["success" => true]);
	die();
