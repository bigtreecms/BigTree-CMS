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

		public static function raw($status, array $payload) {
			$r = new self();
			$r->status = $status;
			$r->body = $payload;

			return $r;
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
