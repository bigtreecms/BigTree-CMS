<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\NotFoundException;

	class Router {
		public static function match($method, $path, array $routes) {
			$method = strtoupper($method);
			$path = "/" . trim($path, "/");
			$path_segments = $path === "/" ? [] : explode("/", trim($path, "/"));

			// Fast path: literal match first.
			$literal_key = $method . " " . $path;
			if (isset($routes[$literal_key])) {
				return ["route" => $routes[$literal_key], "params" => [], "key" => $literal_key];
			}

			// Templated match.
			foreach ($routes as $key => $route) {
				if (strpos($key, " ") === false) continue;
				[$route_method, $route_pattern] = explode(" ", $key, 2);
				if (strtoupper($route_method) !== $method) continue;
				if (strpos($route_pattern, "{") === false) continue;

				$pattern_segments = explode("/", trim($route_pattern, "/"));
				if (count($pattern_segments) !== count($path_segments)) continue;

				$params = [];
				$ok = true;
				foreach ($pattern_segments as $i => $seg) {
					if (strlen($seg) && $seg[0] === "{") {
						// Format: {name} or {name:type}
						$inner = trim($seg, "{}");
						[$name, $type] = array_pad(explode(":", $inner, 2), 2, "string");
						if (!self::matchType($path_segments[$i], $type)) { $ok = false; break; }
						$params[$name] = self::castType($path_segments[$i], $type);
					} elseif ($seg !== $path_segments[$i]) {
						$ok = false;
						break;
					}
				}

				if ($ok) {
					return ["route" => $route, "params" => $params, "key" => $key];
				}
			}

			throw new NotFoundException("No route matches " . $method . " " . $path, "route_not_found", 404);
		}

		private static function matchType($value, $type) {
			switch ($type) {
				case "int":
					return ctype_digit($value);
				case "string":
				default:
					return strlen($value) > 0;
			}
		}

		private static function castType($value, $type) {
			if ($type === "int") return (int)$value;
			return $value;
		}
	}
