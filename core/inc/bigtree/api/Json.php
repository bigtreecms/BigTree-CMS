<?php
	namespace BigTree\Api;

	/**
	 * Decoding helper for JSON-typed storage columns. BigTree persists structured
	 * data (page resources, pending-change diffs, user permissions, image crops, …)
	 * as JSON strings in SQL/JSONDB columns. Reading one back into a PHP array was
	 * hand-rolled across the service layer in several drifting variants:
	 *
	 *   json_decode($row["permissions"], true) ?: []
	 *   json_decode($page["resources"] ?: "{}", true) ?: []
	 *   is_array($row["changes"]) ? $row["changes"] : (json_decode($row["changes"] ?: "[]", true) ?: [])
	 *
	 * Json::decode() folds all three onto one definition: an already-decoded array
	 * passes through, a non-empty JSON string is decoded, and null / "" / invalid
	 * JSON fall back to $default. This also removes the `?: "{}"` pre-guard the
	 * callers only carried to dodge PHP 8.2's "passing null to json_decode"
	 * deprecation.
	 *
	 * Named to sit alongside the request middleware (BigTree\Api\Middleware\Json);
	 * the namespaces are distinct so there is no collision.
	 */
	class Json {
		/**
		 * Decode a JSON-column value to an array.
		 *
		 * @param mixed $value   Raw column value: a JSON string, an already-decoded
		 *                       array, or null/empty.
		 * @param array $default Returned when $value is empty or not valid JSON.
		 * @return array The decoded array, or $default.
		 */
		public static function decode(mixed $value, array $default = []): array {
			if (is_array($value)) {
				return $value;
			}

			if (!is_string($value) || $value === "") {
				return $default;
			}

			$decoded = json_decode($value, true);

			return is_array($decoded) ? $decoded : $default;
		}
	}
