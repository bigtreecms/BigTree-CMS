<?php
	namespace BigTree;
	use Exception;

	/**
	 * BigTree WebAuthn / Passkey support
	 * Implements WebAuthn Level 2 registration and authentication.
	 * Supports ES256 (ECDSA P-256) and RS256 (RSA PKCS1-v1_5) credentials.
	 */
	class WebAuthn {

		private $_cbor_data = '';
		private $_cbor_pos = 0;

		// ─── Public API ─────────────────────────────────────────────────────────

		/**
		 * Generate a fresh 32-byte challenge, store it in the session, and
		 * return it as a base64url string.
		 * @return string
		 */
		public static function generateChallenge() {
			$challenge = static::base64urlEncode(random_bytes(32));
			$_SESSION['bigtree_passkey_challenge'] = $challenge;

			return $challenge;
		}

		/**
		 * Return the PublicKeyCredentialCreationOptions array for a registration ceremony.
		 *
		 * @param string $rp_id               e.g. "example.com"
		 * @param string $rp_name             Human-readable site name
		 * @param int    $user_id             BigTree user ID
		 * @param string $user_name           Usually the user's email
		 * @param string $user_display_name   Usually the user's full name
		 * @param array  $existing_ids        base64url credential IDs already registered (to exclude)
		 * @return array
		 */
		public static function getRegistrationOptions($rp_id, $rp_name, $user_id, $user_name, $user_display_name, $existing_ids = []) {
			return [
				'rp' => ['id' => $rp_id, 'name' => $rp_name],
				'user' => [
					'id' => static::base64urlEncode(pack('N', $user_id)),
					'name' => $user_name,
					'displayName' => $user_display_name,
				],
				'challenge' => static::generateChallenge(),
				'pubKeyCredParams' => [
					['type' => 'public-key', 'alg' => -7],    // ES256
					['type' => 'public-key', 'alg' => -257],  // RS256
				],
				'timeout' => 60000,
				'excludeCredentials' => array_map(function($id) {
					return ['type' => 'public-key', 'id' => $id];
				}, $existing_ids),
				'authenticatorSelection' => [
					'residentKey' => 'required',
					'requireResidentKey' => true,
					'userVerification' => 'preferred',
				],
				'attestation' => 'none',
			];
		}

		/**
		 * Return the PublicKeyCredentialRequestOptions array for an authentication ceremony.
		 *
		 * @param string $rp_id          e.g. "example.com"
		 * @param array  $credential_ids base64url credential IDs that may be used (empty = discoverable)
		 * @return array
		 */
		public static function getAuthenticationOptions($rp_id, $credential_ids = []) {
			return [
				'challenge' => static::generateChallenge(),
				'timeout' => 60000,
				'rpId' => $rp_id,
				'allowCredentials' => array_map(function($id) {
					return ['type' => 'public-key', 'id' => $id];
				}, $credential_ids),
				'userVerification' => 'preferred',
			];
		}

		/**
		 * Verify a WebAuthn registration response.
		 * Returns an array of credential data to store, or throws on failure.
		 *
		 * @param array  $response  Associative array with keys: id, clientDataJSON, attestationObject, transports
		 * @param string $origin    Full origin, e.g. "https://example.com"
		 * @param string $rp_id    RP ID, e.g. "example.com"
		 * @return array ['credential_id', 'public_key', 'sign_count', 'aaguid', 'transports']
		 * @throws Exception
		 */
		public static function verifyRegistration($response, $origin, $rp_id) {
			// 1. Decode and validate clientDataJSON
			$client_data_raw = static::base64urlDecode($response['clientDataJSON']);
			$client_data = json_decode($client_data_raw, true);

			if (($client_data['type'] ?? '') !== 'webauthn.create') {
				throw new Exception('Invalid clientData type');
			}

			$session_challenge = $_SESSION['bigtree_passkey_challenge'] ?? '';
			if (empty($session_challenge) || $client_data['challenge'] !== $session_challenge) {
				throw new Exception('Challenge mismatch');
			}

			if ($client_data['origin'] !== $origin) {
				throw new Exception('Origin mismatch: got '.$client_data['origin'].' expected '.$origin);
			}

			// 2. Decode attestationObject (CBOR)
			$attestation_bytes = static::base64urlDecode($response['attestationObject']);
			$attestation = static::cborDecode($attestation_bytes);

			if (!isset($attestation['authData'])) {
				throw new Exception('Missing authData in attestationObject');
			}

			// 3. Parse authenticatorData
			$auth_data = static::parseAuthenticatorData($attestation['authData']);

			// 4. Verify RP ID hash
			if ($auth_data['rp_id_hash'] !== hash('sha256', $rp_id, true)) {
				throw new Exception('RP ID hash mismatch');
			}

			// 5. Verify flags
			if (!$auth_data['flags']['up']) {
				throw new Exception('User Present flag not set');
			}

			if (!$auth_data['flags']['at'] || !isset($auth_data['public_key_cbor'])) {
				throw new Exception('No attested credential data in response');
			}

			// 6. Convert COSE public key to PEM
			$public_key_pem = static::coseToPublicKey($auth_data['public_key_cbor']);

			// 7. Clear challenge
			unset($_SESSION['bigtree_passkey_challenge']);

			return [
				'credential_id' => $response['id'],
				'public_key' => $public_key_pem,
				'sign_count' => $auth_data['sign_count'],
				'aaguid' => $auth_data['aaguid'],
				'transports' => implode(',', (array) ($response['transports'] ?? [])),
			];
		}

		/**
		 * Verify a WebAuthn authentication assertion.
		 * Returns the new sign count on success, or throws on failure.
		 *
		 * @param array  $response    Keys: clientDataJSON, authenticatorData, signature
		 * @param array  $credential  Row from bigtree_user_passkeys: ['public_key', 'sign_count']
		 * @param string $origin      Full origin, e.g. "https://example.com"
		 * @param string $rp_id       RP ID, e.g. "example.com"
		 * @return int New sign count
		 * @throws Exception
		 */
		public static function verifyAuthentication($response, $credential, $origin, $rp_id) {
			// 1. Decode and validate clientDataJSON
			$client_data_raw = static::base64urlDecode($response['clientDataJSON']);
			$client_data = json_decode($client_data_raw, true);

			if (($client_data['type'] ?? '') !== 'webauthn.get') {
				throw new Exception('Invalid clientData type');
			}

			$session_challenge = $_SESSION['bigtree_passkey_challenge'] ?? '';
			if (empty($session_challenge) || $client_data['challenge'] !== $session_challenge) {
				throw new Exception('Challenge mismatch');
			}

			if ($client_data['origin'] !== $origin) {
				throw new Exception('Origin mismatch');
			}

			// 2. Parse authenticatorData
			$auth_data_raw = static::base64urlDecode($response['authenticatorData']);
			$auth_data = static::parseAuthenticatorData($auth_data_raw);

			// 3. Verify RP ID hash
			if ($auth_data['rp_id_hash'] !== hash('sha256', $rp_id, true)) {
				throw new Exception('RP ID hash mismatch');
			}

			// 4. Verify User Present flag
			if (!$auth_data['flags']['up']) {
				throw new Exception('User Present flag not set');
			}

			// 5. Sign count check (replay attack protection)
			$stored_count = (int) $credential['sign_count'];
			$new_count = (int) $auth_data['sign_count'];
			if ($stored_count > 0 && $new_count > 0 && $new_count <= $stored_count) {
				throw new Exception('Sign count check failed — possible cloned authenticator');
			}

			// 6. Verify signature: sig = sign(authData || SHA-256(clientDataJSON))
			$client_data_hash = hash('sha256', $client_data_raw, true);
			$signed_data = $auth_data_raw . $client_data_hash;
			$signature = static::base64urlDecode($response['signature']);

			$result = openssl_verify($signed_data, $signature, $credential['public_key'], OPENSSL_ALGO_SHA256);
			if ($result !== 1) {
				throw new Exception('Signature verification failed');
			}

			// 7. Clear challenge
			unset($_SESSION['bigtree_passkey_challenge']);

			return $new_count;
		}

		// ─── CBOR Decoder ───────────────────────────────────────────────────────

		/**
		 * Decode a CBOR-encoded byte string.
		 * @param string $bytes  Raw binary string
		 * @return mixed
		 */
		public static function cborDecode($bytes) {
			$instance = new static();
			$instance->_cbor_data = $bytes;
			$instance->_cbor_pos = 0;

			return $instance->_cborDecodeItem();
		}

		private function _cborDecodeItem() {
			if ($this->_cbor_pos >= strlen($this->_cbor_data)) {
				throw new Exception('CBOR: unexpected end of data at position '.$this->_cbor_pos);
			}

			$byte = ord($this->_cbor_data[$this->_cbor_pos++]);
			$type = $byte >> 5;
			$info = $byte & 0x1f;

			switch ($type) {
				case 0: // unsigned integer
					return $this->_cborUint($info);
				case 1: // negative integer
					return -1 - $this->_cborUint($info);
				case 2: // byte string
					$len = $this->_cborUint($info);
					$val = substr($this->_cbor_data, $this->_cbor_pos, $len);
					$this->_cbor_pos += $len;

					return $val;
				case 3: // text string
					$len = $this->_cborUint($info);
					$val = substr($this->_cbor_data, $this->_cbor_pos, $len);
					$this->_cbor_pos += $len;

					return $val;
				case 4: // array
					$count = $this->_cborUint($info);
					$arr = [];

					for ($i = 0; $i < $count; $i++) {
						$arr[] = $this->_cborDecodeItem();
					}

					return $arr;
				case 5: // map
					$count = $this->_cborUint($info);
					$map = [];

					for ($i = 0; $i < $count; $i++) {
						$key = $this->_cborDecodeItem();
						$map[$key] = $this->_cborDecodeItem();
					}

					return $map;
				case 6: // tag — skip tag value and decode the next item
					$this->_cborUint($info);

					return $this->_cborDecodeItem();
				case 7: // simple values / floats
					if ($info === 20) return false;
					if ($info === 21) return true;
					if ($info === 22) return null;
					if ($info === 25) { $this->_cbor_pos += 2; return 0.0; }  // float16
					if ($info === 26) { $this->_cbor_pos += 4; return 0.0; }  // float32
					if ($info === 27) { $this->_cbor_pos += 8; return 0.0; }  // float64

					return null;
			}
			throw new Exception('CBOR: unknown major type '.$type);
		}

		private function _cborUint($info) {
			if ($info < 24) {
				return $info;
			} elseif ($info === 24) {
				return ord($this->_cbor_data[$this->_cbor_pos++]);
			} elseif ($info === 25) {
				$val = unpack('n', substr($this->_cbor_data, $this->_cbor_pos, 2))[1];
				$this->_cbor_pos += 2;

				return $val;
			} elseif ($info === 26) {
				$val = unpack('N', substr($this->_cbor_data, $this->_cbor_pos, 4))[1];
				$this->_cbor_pos += 4;

				return $val;
			} elseif ($info === 27) {
				$hi = unpack('N', substr($this->_cbor_data, $this->_cbor_pos, 4))[1];
				$lo = unpack('N', substr($this->_cbor_data, $this->_cbor_pos + 4, 4))[1];
				$this->_cbor_pos += 8;

				return ($hi * 0x100000000) + $lo;
			}

			throw new Exception('CBOR: unsupported additional info '.$info);
		}

		// ─── Authenticator Data Parser ──────────────────────────────────────────

		/**
		 * Parse raw authenticatorData bytes.
		 *
		 * Layout:
		 *   [0..31]  rpIdHash (32 bytes)
		 *   [32]     flags
		 *   [33..36] signCount (uint32 BE)
		 *   if AT flag set:
		 *     [37..52]  aaguid (16 bytes)
		 *     [53..54]  credentialIdLength (uint16 BE)
		 *     [55..N]   credentialId
		 *     [N+1..]   credentialPublicKey (CBOR)
		 */
		private static function parseAuthenticatorData($data) {
			if (strlen($data) < 37) {
				throw new Exception('authenticatorData is too short');
			}

			$flags_byte = ord($data[32]);

			$result = [
				'rp_id_hash' => substr($data, 0, 32),
				'flags' => [
					'up' => (bool)($flags_byte & 0x01),  // User Present
					'uv' => (bool)($flags_byte & 0x04),  // User Verified
					'at' => (bool)($flags_byte & 0x40),  // Attested credential data present
					'ed' => (bool)($flags_byte & 0x80),  // Extension data present
				],
				'sign_count' => unpack('N', substr($data, 33, 4))[1],
				'aaguid' => '',
			];

			if ($result['flags']['at'] && strlen($data) > 37) {
				$pos = 37;
				$result['aaguid'] = static::bytesToUUID(substr($data, $pos, 16));
				$pos += 16;
				$cred_id_len = unpack('n', substr($data, $pos, 2))[1];
				$pos += 2;
				$result['credential_id_bytes'] = substr($data, $pos, $cred_id_len);
				$pos += $cred_id_len;
				$result['public_key_cbor'] = substr($data, $pos);
			}

			return $result;
		}

		// ─── COSE Key → PEM ─────────────────────────────────────────────────────

		/**
		 * Convert a CBOR-encoded COSE key to a PEM public key string.
		 * Supports EC P-256 (kty=2, alg=-7) and RSA (kty=3, alg=-257).
		 */
		private static function coseToPublicKey($cose_bytes) {
			$key = static::cborDecode($cose_bytes);
			$kty = $key[1] ?? null;

			if ($kty === 2) {
				// EC2 — P-256
				$crv = $key[-1] ?? null;
				$x   = $key[-2] ?? null;
				$y   = $key[-3] ?? null;

				if ($crv !== 1) {
					throw new Exception('Only P-256 (crv=1) supported, got crv='.$crv);
				}

				if (strlen($x) !== 32 || strlen($y) !== 32) {
					throw new Exception('EC key coordinates must be 32 bytes each');
				}

				return static::buildEC2Pem($x, $y);
			} elseif ($kty === 3) {
				// RSA
				$n = $key[-1] ?? null;
				$e = $key[-2] ?? null;

				if (!$n || !$e) {
					throw new Exception('Missing RSA key components (n or e)');
				}

				return static::buildRSAPem($n, $e);
			}

			throw new Exception('Unsupported COSE key type: '.$kty);
		}

		/**
		 * Build an EC P-256 SubjectPublicKeyInfo PEM from raw x, y coordinates.
		 * The DER structure is fixed for P-256 with only the 64 key bytes varying.
		 */
		private static function buildEC2Pem($x, $y) {
			// SEQUENCE(89) {
			//   SEQUENCE(19) { OID ecPublicKey, OID P-256 }
			//   BIT STRING(66) { 0x00, 0x04, x[32], y[32] }
			// }
			$der = "\x30\x59"
			     . "\x30\x13"
			     . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"      // OID id-ecPublicKey
			     . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"  // OID secp256r1
			     . "\x03\x42"
			     . "\x00\x04"
			     . $x . $y;

			return "-----BEGIN PUBLIC KEY-----\n"
			     . chunk_split(base64_encode($der), 64, "\n")
			     . "-----END PUBLIC KEY-----\n";
		}

		/**
		 * Build an RSA SubjectPublicKeyInfo PEM from modulus (n) and exponent (e).
		 */
		private static function buildRSAPem($n, $e) {
			// Ensure positive integers (prepend 0x00 if high bit set)
			$n = ltrim($n, "\x00");

			if (ord($n[0]) >= 0x80) {
				$n = "\x00" . $n;
			}

			$e = ltrim($e, "\x00");
			if (ord($e[0]) >= 0x80) {
				$e = "\x00" . $e;
			}

			$n_der   = "\x02" . static::_derLen(strlen($n)) . $n;
			$e_der   = "\x02" . static::_derLen(strlen($e)) . $e;
			$rsa_key = "\x30" . static::_derLen(strlen($n_der) + strlen($e_der)) . $n_der . $e_der;

			$alg_id    = "\x30\x0d"
			           . "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"  // OID rsaEncryption
			           . "\x05\x00";  // NULL
			$bit_str   = "\x03" . static::_derLen(strlen($rsa_key) + 1) . "\x00" . $rsa_key;
			$spki      = "\x30" . static::_derLen(strlen($alg_id) + strlen($bit_str)) . $alg_id . $bit_str;

			return "-----BEGIN PUBLIC KEY-----\n"
			     . chunk_split(base64_encode($spki), 64, "\n")
			     . "-----END PUBLIC KEY-----\n";
		}

		/** DER length encoding (short and long form). */
		private static function _derLen($len) {
			if ($len < 128) {
				return chr($len);
			} elseif ($len < 256) {
				return "\x81" . chr($len);
			} else {
				return "\x82" . chr(($len >> 8) & 0xff) . chr($len & 0xff);
			}
		}

		// ─── Helpers ─────────────────────────────────────────────────────────────

		/** Convert 16 raw bytes to a UUID string (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx). */
		private static function bytesToUUID($bytes) {
			$h = bin2hex($bytes);

			return substr($h, 0, 8).'-'.substr($h, 8, 4).'-'.substr($h, 12, 4).'-'.substr($h, 16, 4).'-'.substr($h, 20);
		}

		/** Base64URL encode (RFC 4648 §5, no padding). */
		public static function base64urlEncode($data) {
			return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		}

		/** Base64URL decode. */
		public static function base64urlDecode($data) {
			$pad = (4 - strlen($data) % 4) % 4;

			return base64_decode(strtr($data . str_repeat('=', $pad), '-_', '+/'));
		}
	}
