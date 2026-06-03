<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\ApiException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use Throwable;

	class Kernel {
		/**
		 * Entry point for /admin/api/v1/... requests.
		 * Builds the middleware stack, dispatches, and writes the response.
		 *
		 * @param array $path_segments The $bigtree["path"] array from router.php
		 */
		public static function handle(array $path_segments) {
			// Never let PHP warnings/notices print into the response body — they
			// would corrupt the JSON envelope (and break client-side JSON.parse).
			// Errors are still logged; we just stop them from being displayed.
			ini_set("display_errors", "0");

			$request_id = self::ensureRequestId();
			$request = Request::fromGlobals($path_segments);
			$request->request_id = $request_id;

			try {
				// Reject malformed JSON early.
				if (isset($request->body["__json_error__"])) {
					throw new BadRequestException("Malformed JSON body: " . $request->body["__json_error__"], "malformed_json", 400);
				}

				$routes = Manifest::load();
				$match = Router::match($request->method, $request->path, $routes);
				$request->route = $match["route"];
				$request->route_params = $match["params"];

				// Middleware pipeline (in order).
				$pipeline = [
					new Middleware\Cors(),
					new Middleware\Json(),
					new Middleware\RateLimit(),
					new Middleware\Authenticate(),
					new Middleware\Permission(),
					new Middleware\Validate(),
				];

				$response = self::runPipeline($pipeline, $request, function (Request $r) {

					return self::invokeHandler($r);
				});

				// Post-handler middleware: audit.
				(new Middleware\Audit())->after($request, $response);

				self::send($response, $request);
			} catch (ApiException $e) {
				self::sendError($e, $request);
			} catch (Throwable $t) {
				// Unhandled — log to BigTree's error log and return a sterile 500.
				@error_log("[BigTree API] " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine());
				$wrapped = new ApiException("Internal server error", "internal_error", 500);
				self::sendError($wrapped, $request);
			}
		}

		private static function runPipeline(array $pipeline, Request $request, callable $terminal) {
			$next = $terminal;

			foreach (array_reverse($pipeline) as $mw) {
				$current = $next;
				$next = function (Request $r) use ($mw, $current) {

					return $mw->handle($r, $current);
				};
			}

			return $next($request);
		}

		private static function invokeHandler(Request $request) {
			$service = $request->route["service"] ?? null;

			if (!is_array($service) || count($service) !== 2) {
				throw new ApiException("Route has no service handler", "internal_error", 500);
			}

			[$class, $method] = $service;

			if (!class_exists($class) || !method_exists($class, $method)) {
				throw new ApiException("Service handler missing: $class::$method", "internal_error", 500);
			}

			$instance = new $class();
			$result = $instance->$method($request);

			if ($result instanceof Response) {
				return $result;
			}

			if ($result === null) {
				return Response::noContent();
			}

			return Response::ok($result);
		}

		private static function send(Response $response, Request $request) {
			$response->send($request->request_id);
		}

		private static function sendError(ApiException $e, Request $request) {
			$payload = [
				"errors" => [
					array_merge([
						"code" => $e->code_string,
						"message" => $e->getMessage() ?: $e->code_string,
					], $e->details),
				],
				"meta" => ["request_id" => $request->request_id],
			];

			if (!empty($e->details["errors"]) && is_array($e->details["errors"])) {
				// Validation: expose the per-field error list directly.
				$payload["errors"] = $e->details["errors"];
			}

			$response = Response::raw($e->status, $payload);

			if ($e instanceof Exceptions\RateLimitException) {
				$response->header("Retry-After", (string)$e->retry_after);
			}

			$response->header("X-Request-Id", $request->request_id ?: "");
			$response->send(null);
		}

		private static function ensureRequestId() {
			$incoming = $_SERVER["HTTP_X_REQUEST_ID"] ?? "";

			if (preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $incoming)) {
				return $incoming;
			}

			return bin2hex(random_bytes(8));
		}
	}
