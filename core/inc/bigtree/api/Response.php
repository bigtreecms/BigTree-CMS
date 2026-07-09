<?php
	namespace BigTree\Api;

	class Response {
		public $status = 200;
		public $headers = [];
		public $body;          // mixed; encoded later
		public $is_envelope = true;
		public $cookies = [];  // each: [name, value, options]

		public static function ok($data, $meta = []) {
			$r = new self();
			$r->status = 200;
			$r->body = ["data" => $data];

			if ($meta) {
				$r->body["meta"] = $meta;
			}
			return $r;
		}

		public static function created($data, $location = null) {
			$r = self::ok($data);
			$r->status = 201;

			if ($location) {
				$r->headers["Location"] = $location;
			}
			return $r;
		}

		public static function noContent() {
			$r = new self();
			$r->status = 204;
			$r->is_envelope = false;

			return $r;
		}

		public static function notModified(string $etag): self {
			$r = new self();
			$r->status = 304;
			$r->is_envelope = false;
			$r->body = null;
			$r->header("ETag", $etag);

			return $r;
		}

		public static function raw($status, array $payload) {
			$r = new self();
			$r->status = $status;
			$r->body = $payload;

			return $r;
		}

		/**
		 * Build a non-envelope response for streaming a file off disk: no JSON
		 * body, the download headers set, Content-Length read from $path. The
		 * caller streams the bytes with stream() once the response is built.
		 */
		public static function download(string $path, string $filename, string $mime): self {
			$r = new self();
			$r->status = 200;
			$r->is_envelope = false;
			$r->body = null;
			$r
				->header("Content-Type", $mime)
				->header("Content-Disposition", 'attachment; filename="' . $filename . '"')
				->header("Content-Length", (string)@filesize($path))
				->header("Cache-Control", "private, no-store")
				->header("X-Content-Type-Options", "nosniff");

			return $r;
		}

		/**
		 * Emit the headers, stream $path straight to output via readfile (no
		 * buffering, no memory pressure regardless of size), and terminate —
		 * bypassing the Kernel's envelope/audit tail, which is moot for a file
		 * download. Only valid on a response built by download(). Does not return.
		 */
		public function stream(string $path) {
			$this->send(null);

			// readfile streams to output; no buffering required.
			@readfile($path);
			exit;
		}

		public function header($name, $value) {
			$this->headers[$name] = $value;

			return $this;
		}

		public function cookie($name, $value, array $options = []) {
			$this->cookies[] = [$name, $value, $options];

			return $this;
		}

		public function send($request_id = null) {
			if (!headers_sent()) {
				http_response_code($this->status);

				foreach ($this->headers as $k => $v) {
					header($k . ": " . $v);
				}

				foreach ($this->cookies as $c) {
					$opts = $c[2];
					$opts += ["expires" => 0, "path" => "/", "secure" => true, "httponly" => true, "samesite" => "Strict"];
					setcookie($c[0], $c[1], $opts);
				}
			}

			if ($this->status === 204 || $this->body === null) {
				return;
			}

			if (!isset($this->headers["Content-Type"])) {
				if (!headers_sent()) {
					header("Content-Type: application/json; charset=utf-8");
				}
			}

			if ($request_id && is_array($this->body) && isset($this->body["meta"])) {
				$this->body["meta"]["request_id"] = $request_id;
			} elseif ($request_id && is_array($this->body)) {
				$this->body["meta"] = ["request_id" => $request_id];
			}

			echo json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
	}
