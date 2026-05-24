<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\BadRequestException;

	class Pagination {
		const DEFAULT_PER_PAGE = 25;
		const MAX_PER_PAGE = 100;

		public static function offset(Request $request, $max_per_page = self::MAX_PER_PAGE) {
			$page = max(1, (int)($request->query["page"] ?? 1));
			$per_page = max(1, min($max_per_page, (int)($request->query["per_page"] ?? self::DEFAULT_PER_PAGE)));

			return [
				"page" => $page,
				"per_page" => $per_page,
				"offset" => ($page - 1) * $per_page,
				"limit" => $per_page,
			];
		}

		public static function offsetMeta($page, $per_page, $total) {

			return [
				"page" => (int)$page,
				"per_page" => (int)$per_page,
				"total" => (int)$total,
				"pages" => (int)ceil($total / max(1, $per_page)),
			];
		}

		public static function encodeCursor(array $payload, $secret) {
			$json = json_encode($payload);
			$body = self::base64url($json);
			$sig = self::base64url(substr(hash_hmac("sha256", $body, $secret, true), 0, 16));

			return $body . "." . $sig;
		}

		public static function decodeCursor($cursor, $secret) {
			if (!is_string($cursor) || strpos($cursor, ".") === false) {
				throw new BadRequestException("Invalid cursor", "invalid_cursor", 400);
			}

			[$body, $sig] = explode(".", $cursor, 2);
			$expected = self::base64url(substr(hash_hmac("sha256", $body, $secret, true), 0, 16));

			if (!hash_equals($expected, $sig)) {
				throw new BadRequestException("Cursor signature invalid", "invalid_cursor", 400);
			}

			$payload = json_decode(self::base64urlDecode($body), true);

			if (!is_array($payload)) {
				throw new BadRequestException("Cursor payload invalid", "invalid_cursor", 400);
			}

			return $payload;
		}

		public static function cursor(Request $request, $secret, $max_per_page = self::MAX_PER_PAGE) {
			$per_page = max(1, min($max_per_page, (int)($request->query["per_page"] ?? self::DEFAULT_PER_PAGE)));
			$cursor_param = $request->query["cursor"] ?? null;
			$cursor_data = $cursor_param ? self::decodeCursor($cursor_param, $secret) : null;

			return ["per_page" => $per_page, "cursor" => $cursor_data];
		}

		private static function base64url($data) {

			return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
		}

		private static function base64urlDecode($data) {
			$pad = strlen($data) % 4;

			if ($pad) {
				$data .= str_repeat("=", 4 - $pad);
			}
			return base64_decode(strtr($data, "-_", "+/"));
		}
	}
