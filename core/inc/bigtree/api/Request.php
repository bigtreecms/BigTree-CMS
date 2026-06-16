<?php
	namespace BigTree\Api;

	class Request {
		public $method;
		public $path;             // e.g. "/users/42"
		public $segments;         // explode("/", path)
		public $route_params = []; // populated by router on match
		public $query = [];
		public $body = [];
		public $headers = [];
		public $cookies = [];
		public $ip;
		public $user_agent;
		public $request_id;
		public $route;            // matched route definition (array)

		public $user;             // populated by Authenticate middleware: stdClass with id, email, name, level, permissions(decoded array), timezone, token_version
		public $token_claims;     // raw JWT claims
		public $files = [];       // normalized $_FILES for multipart uploads

		public static function fromGlobals($path_segments_from_router) {
			$req = new self();
			$req->method = $_SERVER["REQUEST_METHOD"] ?? "GET";

			// Strip "/admin/api/v1" prefix; path_segments_from_router[0]="api", [1]="v1", rest is the API path.
			// Use $bigtree["path"] starting from index 3.
			$tail = array_slice($path_segments_from_router, 3);
			$req->segments = array_values(array_filter($tail, function ($s) { return $s !== ""; }));
			$req->path = "/" . implode("/", $req->segments);

			$req->query = $_GET;
			$req->cookies = $_COOKIE;
			$req->headers = self::collectHeaders();
			$req->ip = self::clientIp();
			$req->user_agent = $_SERVER["HTTP_USER_AGENT"] ?? "";

			if (in_array($req->method, ["POST", "PUT", "PATCH", "DELETE"], true)) {
				$content_type = $req->headers["content-type"] ?? "";

				if (stripos($content_type, "application/json") !== false) {
					$raw = file_get_contents("php://input");

					if ($raw !== false && $raw !== "") {
						$decoded = json_decode($raw, true);

						if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
							$req->body = $decoded;
						} elseif (json_last_error() !== JSON_ERROR_NONE) {
							// Malformed JSON — leave $body empty so Json middleware can 400.
							$req->body = ["__json_error__" => json_last_error_msg()];
						}
					}
				} elseif (stripos($content_type, "multipart/form-data") !== false) {
					$req->body = $_POST;
					$req->files = self::normalizeFiles($_FILES);
				} else {
					$req->body = $_POST;
				}
			}

			return $req;
		}

		private static function collectHeaders() {
			$headers = [];

			foreach ($_SERVER as $key => $value) {
				if (strncmp($key, "HTTP_", 5) === 0) {
					$name = strtolower(str_replace("_", "-", substr($key, 5)));
					$headers[$name] = $value;
				} elseif ($key === "CONTENT_TYPE" || $key === "CONTENT_LENGTH") {
					$name = strtolower(str_replace("_", "-", $key));
					$headers[$name] = $value;
				}
			}

			return $headers;
		}

		private static function clientIp() {
			// Respect trusted proxies declared in config; fall back to REMOTE_ADDR.
			global $bigtree;
			$trusted = $bigtree["config"]["api"]["trusted_proxies"] ?? [];
			$remote = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
			$xff = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? "";

			return self::resolveForwardedIp($remote, is_array($trusted) ? $trusted : [], $xff);
		}

		/**
		 * Resolve the real client IP from REMOTE_ADDR and the X-Forwarded-For header.
		 *
		 * Pure helper (no $_SERVER/globals) so it can be unit-tested directly.
		 *
		 * X-Forwarded-For is appended left-to-right as a request traverses proxies, so the
		 * leftmost entry is client-controlled and untrustworthy. We only trust XFF at all if
		 * REMOTE_ADDR is one of our declared trusted proxies, then walk the list right-to-left
		 * skipping entries that are themselves trusted proxies and return the first non-trusted
		 * hop — the real client as seen by our trusted edge. Trusted proxies are matched by
		 * exact string only (no CIDR ranges). If every hop is trusted, or the chosen value is
		 * not a syntactically valid IP, we fall back to REMOTE_ADDR so a garbage/spoofed token
		 * can never become a rate-limit or ban key.
		 */
		private static function resolveForwardedIp(string $remote, array $trusted, string $xffHeader): string {
			if (!in_array($remote, $trusted, true) || $xffHeader === "") {

				return $remote;
			}

			$forwarded = array_values(array_filter(array_map("trim", explode(",", $xffHeader)), function ($entry) {

				return $entry !== "";
			}));

			for ($i = count($forwarded) - 1; $i >= 0; $i--) {
				$candidate = $forwarded[$i];

				if (in_array($candidate, $trusted, true)) {
					continue;
				}

				if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {

					return $remote;
				}

				return $candidate;
			}

			return $remote;
		}

		public function header($name) {

			return $this->headers[strtolower($name)] ?? null;
		}

		public function bearer() {
			$auth = $this->header("authorization");

			if ($auth && stripos($auth, "Bearer ") === 0) {
				return trim(substr($auth, 7));
			}

			return null;
		}

		public function file($name) {

			return $this->files[$name] ?? null;
		}

		/**
		 * Normalize $_FILES so single and multi-file uploads have the same shape.
		 * Returns an associative array: name => [ ['name','tmp_name','type','size','error'], ... ]
		 */
		private static function normalizeFiles(array $files) {
			$out = [];

			foreach ($files as $name => $f) {
				if (!is_array($f) || !isset($f["name"])) {
					continue;
				}

				if (is_array($f["name"])) {
					// HTML <input name="x[]" multiple> shape: f["name"][0], f["tmp_name"][0], ...
					$count = count($f["name"]);
					$list = [];

					for ($i = 0; $i < $count; $i++) {
						$list[] = [
							"name" => $f["name"][$i] ?? "",
							"tmp_name" => $f["tmp_name"][$i] ?? "",
							"type" => $f["type"][$i] ?? "",
							"size" => $f["size"][$i] ?? 0,
							"error" => $f["error"][$i] ?? UPLOAD_ERR_NO_FILE,
						];
					}

					$out[$name] = $list;
				} else {
					$out[$name] = [[
						"name" => $f["name"],
						"tmp_name" => $f["tmp_name"],
						"type" => $f["type"] ?? "",
						"size" => $f["size"] ?? 0,
						"error" => $f["error"] ?? UPLOAD_ERR_NO_FILE,
					]];
				}
			}

			return $out;
		}
	}
