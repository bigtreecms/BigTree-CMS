<?php
	namespace BigTree\Api;

	/**
	 * Spec-driven field transforms for JSONDB entity create/update payloads.
	 *
	 * Callers supply a closed `key => verb` map; insert() builds a full row and
	 * update() builds a partial patch (only keys present under the verb's presence
	 * rule). The vocabulary is the same closed set ModuleSubResourceSupport used
	 * for module sub-resources, plus "int" and "clean" for the JSON-DB entity
	 * services (Callout, Template, Feed, FieldType).
	 *
	 * Verbs:
	 *   "encode"    BigTree::safeEncode of the (string) value ("" when absent)
	 *   "string"    plain (string) cast ("" when absent)
	 *   "array"     the array value, or [] when absent / not an array
	 *   "checkbox"  Flag::checkbox (yes/no normalization)
	 *   "nullable"  the truthy value, otherwise null
	 *   "bool"      a plain !empty() boolean
	 *   "int"       (int) cast (0 when absent)
	 *   "clean"     Resources::clean of the array value ([] when absent / not array)
	 *
	 * Presence rules for update() (the isset-vs-array_key_exists subtlety):
	 *   encode/string/int  — isset()
	 *   array/clean        — isset() and is_array()
	 *   checkbox/nullable/bool — array_key_exists() so explicit null/false is honored
	 *
	 * Per-entity specials that don't fit (normalizeUseCases, applyRenderFields,
	 * name-defaults-to-id) stay explicit at the call site.
	 */
	class FieldSpec {
		/**
		 * Build a full INSERT payload from request body $d per $spec.
		 *
		 * @param array $d    Request body.
		 * @param array $spec key => verb map.
		 */
		public static function insert(array $d, array $spec): array {
			$out = [];

			foreach ($spec as $key => $verb) {
				$out[$key] = self::transform($d[$key] ?? null, $verb);
			}

			return $out;
		}

		/**
		 * Build a partial UPDATE payload from $d per $spec: each key is written
		 * only when present under the verb's presence rule.
		 *
		 * @param array $d    Request body.
		 * @param array $spec key => verb map (same vocabulary as insert).
		 */
		public static function update(array $d, array $spec): array {
			$update = [];

			foreach ($spec as $key => $verb) {
				if (!self::present($d, $key, $verb)) {
					continue;
				}

				$update[$key] = self::transform($d[$key] ?? null, $verb);
			}

			return $update;
		}

		/** Apply one transform verb to a raw value. See class docblock for the vocabulary. */
		public static function transform($value, string $verb) {
			switch ($verb) {
				case "encode":

					return \BigTree::safeEncode((string)($value ?? ""));

				case "string":

					return (string)($value ?? "");

				case "array":

					return is_array($value) ? $value : [];

				case "checkbox":

					return Flag::checkbox($value);

				case "nullable":

					return !empty($value) ? $value : null;

				case "bool":

					return !empty($value);

				case "int":

					return (int)($value ?? 0);

				case "clean":

					return Resources::clean(is_array($value) ? $value : []);
			}

			throw new \LogicException("Unknown field verb: $verb");
		}

		/** Whether $d carries $key under the presence rule its verb dictates (see update). */
		public static function present(array $d, string $key, string $verb): bool {
			if ($verb === "array" || $verb === "clean") {

				return isset($d[$key]) && is_array($d[$key]);
			}

			if ($verb === "encode" || $verb === "string" || $verb === "int") {

				return isset($d[$key]);
			}

			// checkbox / nullable / bool: honor an explicitly-submitted null or false.
			return array_key_exists($key, $d);
		}
	}
