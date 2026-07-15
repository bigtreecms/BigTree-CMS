<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Response;

	class Cors {
		public function handle(Request $request, callable $next) {
			$cors = self::headersFor($request);

			if ($request->method === "OPTIONS") {
				$response = Response::raw(204, []);
				$response->is_envelope = false;
				$response->body = null;

				if ($cors) {
					$response->header("Access-Control-Allow-Methods", "GET, POST, PATCH, PUT, DELETE, OPTIONS")
						->header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Request-Id, If-None-Match")
						->header("Access-Control-Max-Age", "600");

					foreach ($cors as $name => $value) {
						$response->header($name, $value);
					}
				}

				return $response;
			}

			$response = $next($request);

			foreach ($cors as $name => $value) {
				$response->header($name, $value);
			}

			return $response;
		}

		/**
		 * The response-time CORS headers to apply for $request, or [] when the origin
		 * isn't allowed. Shared by handle() and by streaming endpoints that emit their
		 * own headers (bypassing the Kernel's Response::send tail), so the same
		 * allow-decision governs both.
		 *
		 * An exact origin match may carry credentials. A wildcard ("*") match may NOT:
		 * per the Fetch spec, "Access-Control-Allow-Origin: *" is incompatible with
		 * credentials, and reflecting an arbitrary origin *with* credentials would let
		 * any site make authenticated cross-origin calls. So wildcard configs serve
		 * the literal "*" and omit credentials.
		 *
		 * @return array<string,string>
		 */
		public static function headersFor(Request $request): array {
			$origin = $request->header("origin");
			$allowed = self::allowedOrigins();

			$exact_match = $origin && in_array($origin, $allowed, true);
			$wildcard = in_array("*", $allowed, true);
			$is_allowed = $exact_match || ($origin && $wildcard);

			if (!$is_allowed) {

				return [];
			}

			$headers = ["Access-Control-Allow-Origin" => $exact_match ? $origin : "*"];

			if ($exact_match) {
				$headers["Access-Control-Allow-Credentials"] = "true";
				$headers["Vary"] = "Origin";
			}

			return $headers;
		}

		private static function allowedOrigins() {
			global $bigtree;
			$origins = [];
			$root = rtrim($bigtree["config"]["www_root"] ?? "", "/");

			if ($root) {
				$origins[] = $root;
			}

			foreach (($bigtree["config"]["sites"] ?? []) as $site) {
				if (!empty($site["www_root"])) {
					$origins[] = rtrim($site["www_root"], "/");
				}
			}

			$extra = $bigtree["config"]["api"]["cors_origins"] ?? [];

			foreach ($extra as $o) {
				$origins[] = rtrim($o, "/");
			}

			return array_values(array_unique($origins));
		}
	}
