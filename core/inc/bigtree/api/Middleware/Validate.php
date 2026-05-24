<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Validator;

	class Validate {
		public function handle(Request $request, callable $next) {
			$route = $request->route;

			$body_rules = $route["body"] ?? null;
			$query_rules = $route["query"] ?? null;
			$strict_unknown = !($route["allow_unknown"] ?? false);
			$is_multipart = !empty($route["multipart"]);

			// Multipart bodies are validated by the handler (files live in $request->files,
			// the rest of $_POST in $request->body but may contain JSON-encoded strings).
			if (is_array($body_rules) && !$is_multipart) {
				$request->body = Validator::validate($request->body, $body_rules, !$strict_unknown);
			}

			if (is_array($query_rules)) {
				$request->query = Validator::validate($request->query, $query_rules, true);
			}

			return $next($request);
		}
	}
