<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Exceptions\BadRequestException;

	class Json {
		public function handle(Request $request, callable $next) {
			if (in_array($request->method, ["POST", "PUT", "PATCH"], true)) {
				$ct = $request->header("content-type") ?? "";
				$is_json = stripos($ct, "application/json") !== false;
				$is_multipart = stripos($ct, "multipart/form-data") !== false;
				$expects_multipart = !empty($request->route["multipart"]);

				if ($expects_multipart && !$is_multipart) {
					throw new BadRequestException("Expected multipart/form-data", "expected_multipart", 400);
				}

				if (!$expects_multipart && !$is_json) {
					throw new BadRequestException("Expected Content-Type: application/json", "expected_json", 400);
				}
			}

			return $next($request);
		}
	}
