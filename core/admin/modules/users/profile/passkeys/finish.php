<?php
	/**
	 * Completes a WebAuthn registration and stores the new passkey.
	 * Expects JSON body: { id, clientDataJSON, attestationObject, transports, name }
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

	if (empty($body["id"]) || empty($body["clientDataJSON"]) || empty($body["attestationObject"])) {
		echo json_encode(["success" => false, "error" => "Missing required fields"]);
		die();
	}

	$parsed = parse_url(ADMIN_ROOT);
	$origin = $parsed["scheme"]."://".$parsed["host"];
	$rp_id  = $parsed["host"];

	try {
		$credential = BigTree\WebAuthn::verifyRegistration($body, $origin, $rp_id);
	} catch (Exception $e) {
		echo json_encode(["success" => false, "error" => $e->getMessage()]);
		die();
	}

	// Use the name the user gave it, falling back to a timestamp-based label
	$name = trim($body["name"] ?? "");
	if (!$name) {
		$name = "Passkey registered ".date("M j, Y");
	}

	BigTreeAdmin::createPasskey(
		$admin->ID,
		$credential["credential_id"],
		$credential["public_key"],
		$credential["sign_count"],
		htmlspecialchars($name, ENT_QUOTES),
		$credential["aaguid"],
		$credential["transports"]
	);

	echo json_encode(["success" => true]);
	die();
