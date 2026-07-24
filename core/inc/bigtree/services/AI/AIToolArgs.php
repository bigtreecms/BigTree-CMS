<?php
	namespace BigTree\Services\AI;

	/**
	 * The argument-shape contract at the model↔backend boundary.
	 *
	 * Every AI tool publishes a JSON schema in definition(), but until this class
	 * ran nothing checked that the arguments a model actually sent matched it. Each
	 * seam did its own permissive cast — `(array)$x` or `is_array($x) ? $x : []` —
	 * so a wrongly-shaped argument was silently coerced to empty. For an additive
	 * argument that means "reported success, wrote nothing"; for a replace-semantics
	 * argument (`fields` on update_template / update_callout) it means "delete
	 * everything".
	 *
	 * The single most common provider defect is a nested value emitted as a JSON
	 * *string* where the schema declares an object/array — most likely exactly on
	 * the schema-less `object` arguments (content / data), which is what makes a
	 * provider fall back to a string in the first place. validate() repairs that one
	 * case (a single json_decode that must land on the declared type) and otherwise
	 * returns a recoverable error naming the argument and its expected shape, so the
	 * model can retry exactly as it does for a required-field error.
	 *
	 * Pure and DB-free: it reads only the tool definition and the argument array.
	 * Run once in AIToolRegistry::execute() before dispatch, so it covers extension
	 * tools too and cannot drift tool by tool.
	 */
	class AIToolArgs {
		/**
		 * Validate (and where safe, repair) $args against a tool's published schema.
		 * Mutates $args in place — a stringified object/array that decodes to the
		 * declared type is replaced with the decoded value. Returns null when the
		 * arguments are acceptable, or a recoverable error message otherwise.
		 *
		 * @param array<string,mixed> $definition The tool's definition() output.
		 * @param array<string,mixed> $args        Model-supplied arguments (by ref).
		 */
		public static function validate(array $definition, array &$args): ?string {
			$parameters = $definition["function"]["parameters"] ?? [];

			if (!is_array($parameters)) {

				return null;
			}

			$properties = is_array($parameters["properties"] ?? null) ? $parameters["properties"] : [];
			$required = is_array($parameters["required"] ?? null) ? $parameters["required"] : [];

			foreach ($required as $key) {
				if (!array_key_exists($key, $args) || $args[$key] === null) {

					return "Missing required argument \"{$key}\".";
				}
			}

			foreach ($properties as $key => $spec) {
				if (!array_key_exists($key, $args) || $args[$key] === null || !is_array($spec)) {

					continue;
				}

				$type = $spec["type"] ?? null;

				if (!is_string($type)) {

					continue;
				}

				$error = self::checkProperty($key, $type, $args[$key], $args);

				if ($error !== null) {

					return $error;
				}
			}

			return null;
		}

		/**
		 * Type-check one declared property, repairing a stringified object/array in
		 * place when it decodes cleanly to the declared type. Returns null when the
		 * value is (or was repaired to) acceptable, else a recoverable error.
		 *
		 * @param mixed               $value
		 * @param array<string,mixed> $args  The argument array, for in-place repair.
		 */
		private static function checkProperty(string $key, string $type, $value, array &$args): ?string {
			switch ($type) {
				case "object":
				case "array":
					return self::checkComposite($key, $type, $value, $args);

				case "string":

					// A model that sends an object/array where a string is declared has
					// misunderstood the field; a number/bool stringifies losslessly, so
					// only the composite case is a real error.
					if (is_array($value)) {

						return "Argument \"{$key}\" must be text, not an object or list.";
					}

					return null;

				case "integer":
				case "number":

					if (is_array($value) || is_bool($value)) {

						return "Argument \"{$key}\" must be a number.";
					}

					if (is_string($value) && !is_numeric(trim($value))) {

						return "Argument \"{$key}\" must be a number.";
					}

					return null;

				case "boolean":

					if (is_array($value)) {

						return "Argument \"{$key}\" must be true or false.";
					}

					return null;

				default:

					return null;
			}
		}

		/**
		 * Check an object/array property. An array value is accepted as-is. A string
		 * value gets exactly one json_decode repair attempt, accepted only if it
		 * decodes to an array (the JSON representation of both object and array). Any
		 * other shape is refused with a message naming the expected shape.
		 *
		 * @param mixed               $value
		 * @param array<string,mixed> $args
		 */
		private static function checkComposite(string $key, string $type, $value, array &$args): ?string {
			if (is_array($value)) {

				return null;
			}

			$shape = $type === "array" ? "a list" : "an object";

			if (is_string($value)) {
				$trimmed = trim($value);

				if ($trimmed !== "") {
					$decoded = json_decode($trimmed, true);

					if (is_array($decoded)) {

						// The common provider quirk: a nested value emitted as a JSON
						// string. Repair it once, in place, and carry on.
						$args[$key] = $decoded;

						return null;
					}
				}
			}

			return "Argument \"{$key}\" must be {$shape}. It arrived as "
				. self::describeType($value) . "; send it as {$shape}"
				. ($type === "array" ? " of values" : " of field/value pairs")
				. ", not as text.";
		}

		/**
		 * Human name for a value's type, for the error message.
		 *
		 * @param mixed $value
		 */
		private static function describeType($value): string {
			if (is_string($value)) {

				return "text";
			}

			if (is_bool($value)) {

				return "true/false";
			}

			if (is_int($value) || is_float($value)) {

				return "a number";
			}

			return gettype($value);
		}
	}
