<?php
	namespace BigTree\Api;

	/**
	 * Base64url (RFC 4648 §5) encode/decode — unpadded, URL-safe alphabet.
	 *
	 * This is the string primitive underneath every signed token in the API: JWTs
	 * (Jwt), HMAC pagination cursors, and the public download tokens that ride the
	 * cursor codec. It lived as byte-identical private twins in Jwt and Pagination;
	 * naming it once keeps the codec single-sourced so a future change lands in one
	 * place.
	 */
	final class Base64Url {
		public static function encode(string $data): string {

			return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
		}

		public static function decode(string $data): string {
			$pad = strlen($data) % 4;

			if ($pad) {
				$data .= str_repeat("=", 4 - $pad);
			}

			return base64_decode(strtr($data, "-_", "+/"));
		}
	}
