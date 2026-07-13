<?php
	namespace BigTree\Services;

	use SQL;

	/**
	 * WebAuthn passkey storage helpers. Moved from legacy admin base class (cluster B).
	 * loginPasskey stays on the legacy facade (API has its own flow in AuthService).
	 */
	class PasskeyService {

		/*
			Function: passkeysEnabled
				Whether passkeys can be used (HTTPS admin root + openssl).

			Returns:
				Boolean
		*/
		public static function passkeysEnabled() {
			global $bigtree;

			return strpos($bigtree["config"]["admin_root"], "https://") === 0 && extension_loaded("openssl");
		}

		/*
			Function: getUserPasskeys
				Returns all passkeys registered by a user.

			Parameters:
				user_id - The user's ID

			Returns:
				Array of passkey rows
		*/
		public static function getUserPasskeys($user_id) {
			return SQL::fetchAll("SELECT * FROM bigtree_user_passkeys WHERE user = ? ORDER BY created_at DESC", $user_id);
		}

		/*
			Function: getPasskeyByCredentialId
				Looks up a passkey by its credential_id.

			Parameters:
				credential_id - The base64url credential ID from the browser

			Returns:
				Passkey row including public_key and sign_count, or false
		*/
		public static function getPasskeyByCredentialId($credential_id) {
			return SQL::fetch("SELECT p.*, u.id AS user_id, u.email, u.name AS user_name,
			                          u.level, u.permissions
			                   FROM bigtree_user_passkeys p
			                   JOIN bigtree_users u ON u.id = p.user
			                   WHERE p.credential_id = ?", $credential_id);
		}

		/*
			Function: createPasskey
				Stores a newly registered passkey.

			Parameters:
				user_id       - The user's ID
				credential_id - base64url credential ID
				public_key    - PEM public key string
				sign_count    - Initial sign count
				name          - Human-readable label (e.g. "Touch ID on MacBook")
				aaguid        - Authenticator AAGUID UUID string
				transports    - Comma-separated transport list

			Returns:
				New passkey ID
		*/
		public static function createPasskey($user_id, $credential_id, $public_key, $sign_count, $name, $aaguid, $transports) {
			return SQL::insert("bigtree_user_passkeys", [
				"user"          => $user_id,
				"credential_id" => $credential_id,
				"public_key"    => $public_key,
				"sign_count"    => $sign_count,
				"name"          => $name,
				"aaguid"        => $aaguid,
				"transports"    => $transports,
			]);
		}

		/*
			Function: deletePasskey
				Deletes a passkey owned by the given user.

			Parameters:
				id      - Passkey row ID
				user_id - Must match the passkey's user to prevent cross-user deletion
		*/
		public static function deletePasskey($id, $user_id) {
			SQL::delete("bigtree_user_passkeys", ["id" => $id, "user" => $user_id]);
		}

		/*
			Function: updatePasskeyUsed
				Updates the sign_count and last_used timestamp after a successful authentication.

			Parameters:
				id         - Passkey row ID
				sign_count - New sign count from the authenticator
		*/
		public static function updatePasskeyUsed($id, $sign_count) {
			SQL::update("bigtree_user_passkeys", $id, [
				"sign_count" => $sign_count,
				"last_used"  => date("Y-m-d H:i:s"),
			]);
		}
	}
