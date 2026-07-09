<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\ValidationException;

	/**
	 * Tiny declarative validator. Rule strings: "required|email|max:255|in:a,b,c".
	 * Returns the cleaned (cast) value set. Throws ValidationException with field-level errors.
	 *
	 * Supported rules:
	 *  required, string, int, bool, email, array, min:N (length for string, value for int),
	 *  max:N, in:a,b,c, enum:a,b,c (alias for in), regex:/.../, url
	 */
	class Validator {
		public static function validate(array $input, array $rules, $allow_unknown = false) {
			$errors = [];
			$out = [];

			foreach ($rules as $field => $rule_string) {
				$rules_list = explode("|", $rule_string);
				$has_value = array_key_exists($field, $input);
				$value = $has_value ? $input[$field] : null;
				$required = in_array("required", $rules_list, true);

				if (!$has_value || $value === "" || $value === null) {
					if ($required) {
						$errors[] = ["code" => "validation_failed", "field" => $field, "message" => "Field is required."];
					}

					continue;
				}

				foreach ($rules_list as $rule) {
					if ($rule === "required") {
						continue;
					}

					[$name, $arg] = array_pad(explode(":", $rule, 2), 2, null);
					$err = self::checkRule($name, $arg, $value);

					if ($err !== null) {
						$errors[] = ["code" => "validation_failed", "field" => $field, "message" => $err];
						break;
					}

					$value = self::castRule($name, $arg, $value);
				}

				$out[$field] = $value;
			}

			if (!$allow_unknown) {
				$unknown = array_diff(array_keys($input), array_keys($rules));

				foreach ($unknown as $k) {
					if ($k === "__json_error__") {
						continue;
					}

					$errors[] = ["code" => "validation_failed", "field" => $k, "message" => "Unknown field."];
				}
			} else {
				foreach ($input as $k => $v) {
					if (!isset($out[$k]) && $k !== "__json_error__") {
						$out[$k] = $v;
					}
				}
			}

			if ($errors) {
				throw new ValidationException($errors);
			}

			return $out;
		}

		private static function checkRule($name, $arg, $value) {
			switch ($name) {
				case "string":
					return is_string($value) || is_numeric($value) ? null : "Must be a string.";
				case "int":
					return (is_int($value) || (is_string($value) && ctype_digit(ltrim($value, "-")))) ? null : "Must be an integer.";
				case "bool":
					return is_bool($value) || in_array($value, ["0", "1", 0, 1, "true", "false", true, false], true) ? null : "Must be a boolean.";
				case "email":
					return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Must be a valid email.";
				case "url":
					return filter_var($value, FILTER_VALIDATE_URL) ? null : "Must be a valid URL.";
				case "array":
					return is_array($value) ? null : "Must be an array.";
				case "min":
					if (is_numeric($value)) {
						return ((float)$value >= (float)$arg) ? null : "Must be at least $arg.";
					}

					return (mb_strlen((string)$value) >= (int)$arg) ? null : "Must be at least $arg characters.";
				case "max":
					if (is_numeric($value)) {
						return ((float)$value <= (float)$arg) ? null : "Must be at most $arg.";
					}

					return (mb_strlen((string)$value) <= (int)$arg) ? null : "Must be at most $arg characters.";
				case "in":
				case "enum":
					$opts = explode(",", $arg);

					return in_array((string)$value, $opts, true) ? null : "Must be one of: " . $arg;
				case "regex":
					return preg_match($arg, (string)$value) ? null : "Does not match required pattern.";
				default:
					return null;
			}
		}

		private static function castRule($name, $arg, $value) {
			switch ($name) {
				case "int":
					return (int)$value;
				case "bool":
					if (is_bool($value)) {
						return $value;
					}

					return in_array($value, ["1", 1, "true", true], true);
				default:
					return $value;
			}
		}
	}
