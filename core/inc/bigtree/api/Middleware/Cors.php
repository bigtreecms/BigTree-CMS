<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Response;

	class Cors {
		public function handle(Request $request, callable $next) {
			$origin = $request->header("origin");
			$allowed = $this->allowedOrigins();

			$is_allowed = $origin && (in_array("*", $allowed, true) || in_array($origin, $allowed, true));

			if ($request->method === "OPTIONS") {
				$response = Response::raw(204, []);
				$response->is_envelope = false;
				$response->body = null;
				if ($is_allowed) {
					$response->header("Access-Control-Allow-Origin", $origin)
						->header("Access-Control-Allow-Credentials", "true")
						->header("Access-Control-Allow-Methods", "GET, POST, PATCH, PUT, DELETE, OPTIONS")
						->header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Request-Id, If-None-Match")
						->header("Access-Control-Max-Age", "600");
				}
				return $response;
			}

			$response = $next($request);

			if ($is_allowed) {
				$response->header("Access-Control-Allow-Origin", $origin)
					->header("Access-Control-Allow-Credentials", "true")
					->header("Vary", "Origin");
			}

			return $response;
		}

		private function allowedOrigins() {
			global $bigtree;
			$origins = [];
			$root = rtrim($bigtree["config"]["www_root"] ?? "", "/");
			if ($root) $origins[] = $root;
			foreach (($bigtree["config"]["sites"] ?? []) as $site) {
				if (!empty($site["www_root"])) $origins[] = rtrim($site["www_root"], "/");
			}
			$extra = $bigtree["config"]["api"]["cors_origins"] ?? [];
			foreach ($extra as $o) $origins[] = rtrim($o, "/");
			return array_values(array_unique($origins));
		}
	}
