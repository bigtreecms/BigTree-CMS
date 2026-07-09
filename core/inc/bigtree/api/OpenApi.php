<?php
	namespace BigTree\Api;

	/**
	 * Pure static builder that turns the route-manifest array into an OpenAPI 3.0.3
	 * document (as a PHP array). No globals, no DB, no filesystem access.
	 *
	 * Rule-string → JSON Schema mapping mirrors Validator::checkRule semantics.
	 * When Validator gains a new rule, extend schemaForRules() to match and update
	 * the comment referencing Validator::checkRule below.
	 *
	 * @see Validator::checkRule
	 */
	class OpenApi {

		/**
		 * Build and return an OpenAPI 3.0.3 document from the manifest route array.
		 *
		 * @param array $routes  Keyed "METHOD /path" => route definition array.
		 * @return array
		 */
		public static function build(array $routes): array {
			$paths = [];

			foreach ($routes as $key => $route) {
				$space = strpos($key, " ");

				if ($space === false) {
					continue;
				}

				$method = strtolower(substr($key, 0, $space));
				$raw_path = substr($key, $space + 1);

				// Strip :type from {name:type} path params — OpenAPI wants {name}.
				$path = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*):[^}]+\}/', '{$1}', $raw_path);

				if (!isset($paths[$path])) {
					$paths[$path] = [];
				}

				$paths[$path][$method] = self::buildOperation($raw_path, $route);
			}

			return [
				"openapi" => "3.0.3",
				"info" => [
					"title" => "BigTree REST API",
					"version" => "1.0.0",
				],
				"servers" => [
					["url" => "/admin/api/v1"],
				],
				"components" => [
					"securitySchemes" => [
						"bearerAuth" => [
							"type" => "http",
							"scheme" => "bearer",
							"bearerFormat" => "JWT",
						],
					],
				],
				"paths" => $paths,
			];
		}

		// — Private helpers —

		/**
		 * Build a single operation object for one route.
		 *
		 * @param string $raw_path  Original path string (with :type tokens).
		 * @param array  $route     Route definition array.
		 * @return array
		 */
		private static function buildOperation(string $raw_path, array $route): array {
			$permission = $route["permission"] ?? null;
			$is_public = $permission === "public";

			$parameters = self::buildPathParameters($raw_path);

			if (!empty($route["query"]) && is_array($route["query"])) {
				foreach ($route["query"] as $field => $rules) {
					$parameters[] = self::buildQueryParameter($field, (string)$rules);
				}
			}

			$operation = [];

			if ($parameters) {
				$operation["parameters"] = $parameters;
			}

			if (!empty($route["body"]) && is_array($route["body"])) {
				$operation["requestBody"] = [
					"required" => true,
					"content" => [
						"application/json" => [
							"schema" => self::buildBodySchema($route["body"]),
						],
					],
				];
			}

			if (!$is_public) {
				$operation["security"] = [["bearerAuth" => []]];
			}

			$responses = [
				"200" => ["description" => "Success"],
				"400" => ["description" => "Bad request"],
				"422" => ["description" => "Validation failed"],
			];

			if (!$is_public) {
				$responses["401"] = ["description" => "Unauthenticated"];
			}

			$operation["responses"] = $responses;

			if (!empty($route["rate_limit"]) && is_array($route["rate_limit"])) {
				$per_minute = $route["rate_limit"]["per_minute"] ?? null;

				if ($per_minute !== null) {
					$operation["description"] = "Rate limited to " . (int)$per_minute . " requests per minute.";
				}
			}

			return $operation;
		}

		/**
		 * Parse {name:type} segments from a raw path and return path parameter objects.
		 *
		 * @param string $raw_path
		 * @return array
		 */
		private static function buildPathParameters(string $raw_path): array {
			$params = [];

			if (!preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*):([^}]+)\}/', $raw_path, $matches, PREG_SET_ORDER)) {
				return $params;
			}

			foreach ($matches as $m) {
				$name = $m[1];
				$type = $m[2];

				$schema = ["type" => ($type === "int") ? "integer" : "string"];

				$params[] = [
					"name" => $name,
					"in" => "path",
					"required" => true,
					"schema" => $schema,
				];
			}

			return $params;
		}

		/**
		 * Build a single query parameter object from a field name and rule string.
		 *
		 * @param string $field
		 * @param string $rules
		 * @return array
		 */
		private static function buildQueryParameter(string $field, string $rules): array {
			$schema = self::schemaForRules($rules);
			$required = in_array("required", explode("|", $rules), true);

			$param = [
				"name" => $field,
				"in" => "query",
				"required" => $required,
				"schema" => $schema,
			];

			return $param;
		}

		/**
		 * Build the JSON Schema object for a request body (application/json).
		 *
		 * @param array $body  Associative field => rule-string map.
		 * @return array
		 */
		private static function buildBodySchema(array $body): array {
			$properties = [];
			$required = [];

			foreach ($body as $field => $rules) {
				$properties[$field] = self::schemaForRules((string)$rules);

				if (in_array("required", explode("|", (string)$rules), true)) {
					$required[] = $field;
				}
			}

			$schema = ["type" => "object", "properties" => $properties];

			if ($required) {
				$schema["required"] = $required;
			}

			return $schema;
		}

		/**
		 * Convert a pipe-delimited rule string into a JSON Schema property object.
		 * Mirrors Validator::checkRule semantics — keep the two in sync.
		 *
		 * @param string $rules  e.g. "required|email|max:255"
		 * @return array
		 */
		private static function schemaForRules(string $rules): array {
			$list = explode("|", $rules);
			$schema = [];
			$type = "string"; // default per Validator convention
			$is_int = false;

			// First pass: determine type so min/max know which constraint to apply.
			foreach ($list as $rule) {
				$name = explode(":", $rule, 2)[0];

				switch ($name) {
					case "int":
						$type = "integer";
						$is_int = true;
						break;
					case "bool":
						$type = "boolean";
						break;
					case "array":
						$type = "array";
						break;
					case "email":
						$type = "string";
						$schema["format"] = "email";
						break;
					case "url":
						$type = "string";
						$schema["format"] = "uri";
						break;
					case "string":
						$type = "string";
						break;
				}
			}

			$schema["type"] = $type;

			// Second pass: constraints that depend on the resolved type.
			foreach ($list as $rule) {
				$parts = explode(":", $rule, 2);
				$name = $parts[0];
				$arg = isset($parts[1]) ? $parts[1] : null;

				switch ($name) {
					case "max":
						if ($arg !== null) {
							$schema[$is_int ? "maximum" : "maxLength"] = (int)$arg;
						}

						break;
					case "min":
						if ($arg !== null) {
							$schema[$is_int ? "minimum" : "minLength"] = (int)$arg;
						}

						break;
					case "in":
					case "enum":
						if ($arg !== null) {
							$schema["enum"] = explode(",", $arg);
						}

						break;
					case "regex":
						if ($arg !== null) {
							$pattern = self::extractRegexPattern($arg);

							if ($pattern !== null) {
								$schema["pattern"] = $pattern;
							}
						}

						break;
				}
			}

			return $schema;
		}

		/**
		 * Strip PHP regex delimiters and flags from a regex string.
		 * Returns null on any doubt so we never emit an invalid pattern.
		 *
		 * @param string $regex  e.g. "/^[a-z]+$/i"
		 * @return string|null
		 */
		private static function extractRegexPattern(string $regex): ?string {
			if (strlen($regex) < 2) {
				return null;
			}

			$delimiter = $regex[0];

			// Only support delimiter characters that are not alphanumeric or backslash.
			if (ctype_alnum($delimiter) || $delimiter === "\\") {
				return null;
			}

			$closing = strrpos($regex, $delimiter, 1);

			if ($closing === false || $closing === 0) {
				return null;
			}

			return substr($regex, 1, $closing - 1);
		}
	}
