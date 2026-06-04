<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Response;

	class Cors {
		public function handle(Request $request, callable $next) {
			$origin = $request->header("origin");
			$allowed = $this->allowedOrigins();

			// An exact origin match may carry credentials. A wildcard ("*") match may
			// NOT: per the Fetch spec, "Access-Control-Allow-Origin: *" is incompatible
			// with credentials, and reflecting an arbitrary origin *with* credentials
			// would let any site make authenticated cross-origin calls. So wildcard
			// configs serve the literal "*" and omit credentials.
			$exact_match = $origin && in_array($origin, $allowed, true);
			$wildcard = in_array("*", $allowed, true);
			$is_allowed = $exact_match || ($origin && $wildcard);
			$allow_origin = $exact_match ? $origin : "*";

			if ($request->method === "OPTIONS") {
				$response = Response::raw(204, []);
				$response->is_envelope = false;
				$response->body = null;

				if ($is_allowed) {
					$response->header("Access-Control-Allow-Origin", $allow_origin)
						->header("Access-Control-Allow-Methods", "GET, POST, PATCH, PUT, DELETE, OPTIONS")
						->header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Request-Id, If-None-Match")
						->header("Access-Control-Max-Age", "600");

					if ($exact_match) {
						$response->header("Access-Control-Allow-Credentials", "true")
							->header("Vary", "Origin");
					}
				}

				return $response;
			}

			$response = $next($request);

			if ($is_allowed) {
				$response->header("Access-Control-Allow-Origin", $allow_origin);

				if ($exact_match) {
					$response->header("Access-Control-Allow-Credentials", "true")
						->header("Vary", "Origin");
				}
			}

			return $response;
		}

		private function allowedOrigins() {
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

			foreach ($extra as $o) $origins[] = rtrim($o, "/");
			return array_values(array_unique($origins));
		}
	}
