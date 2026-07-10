<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\BadRequestException;
	use SQL;

	class Pagination {
		const DEFAULT_PER_PAGE = 25;
		const MAX_PER_PAGE = 100;

		/**
		 * Run the offset-pagination spine shared by the list endpoints: resolve the
		 * page window, COUNT the total, fetch the page slice, map rows through
		 * $present, and wrap it in Response::ok with offset meta.
		 *
		 * Callers pass the two fully-formed queries (so joins, aliases, and custom
		 * WHERE clauses stay under their control and the SQL is unchanged); the
		 * shared $args bind both, and LIMIT/OFFSET is appended to $rows_query here.
		 *
		 * $prepare lets a caller run one batched step over the page rows before they
		 * are mapped (e.g. a single name lookup keyed by id); its return value is
		 * passed to $present as a second argument. Present callbacks that only take
		 * one parameter simply ignore it, so existing callers are unaffected.
		 *
		 * @param string        $count_query "SELECT COUNT(*) FROM …" with "?" placeholders.
		 * @param string        $rows_query  "SELECT … FROM … ORDER BY …" WITHOUT LIMIT/OFFSET.
		 * @param array         $args        Values bound to both queries' placeholders.
		 * @param callable      $present     Row mapper: present($row, $context).
		 * @param callable|null $prepare     Optional one-shot over the page rows; its
		 *                                   result becomes $present's $context.
		 */
		public static function paginate(Request $request, string $count_query, string $rows_query, array $args, callable $present, int $max_per_page = self::MAX_PER_PAGE, ?callable $prepare = null): Response {
			$p = self::offset($request, $max_per_page);

			$total = (int)SQL::fetchSingle(...array_merge([$count_query], $args));

			$paged = $rows_query . " LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"];
			$rows = SQL::fetchAll(...array_merge([$paged], $args));

			$context = $prepare !== null ? $prepare($rows) : null;
			$items = array_map(fn ($row) => $present($row, $context), $rows);

			return Response::ok($items, self::offsetMeta($p["page"], $p["per_page"], $total));
		}

		/**
		 * In-memory sibling of paginate() for endpoints whose full result set is
		 * already assembled in PHP (e.g. JSONDB definitions filtered/sorted in code):
		 * resolve the page window, slice $rows, map the slice through $present, and
		 * wrap it in Response::ok with offset meta. The total reflects the whole
		 * passed-in set, not the slice.
		 *
		 * @param array    $rows    The complete, already-ordered result set.
		 * @param callable $present Row mapper applied to each row in the page slice.
		 */
		public static function paginateRows(Request $request, array $rows, callable $present, int $max_per_page = self::MAX_PER_PAGE): Response {
			$p = self::offset($request, $max_per_page);

			$total = count($rows);
			$slice = array_slice($rows, $p["offset"], $p["limit"]);
			$items = array_map($present, $slice);

			return Response::ok($items, self::offsetMeta($p["page"], $p["per_page"], $total));
		}

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
			$body = Base64Url::encode($json);
			$sig = Base64Url::encode(substr(hash_hmac("sha256", $body, $secret, true), 0, 16));

			return $body . "." . $sig;
		}

		public static function decodeCursor($cursor, $secret) {
			if (!is_string($cursor) || strpos($cursor, ".") === false) {
				throw new BadRequestException("Invalid cursor", "invalid_cursor");
			}

			[$body, $sig] = explode(".", $cursor, 2);
			$expected = Base64Url::encode(substr(hash_hmac("sha256", $body, $secret, true), 0, 16));

			if (!hash_equals($expected, $sig)) {
				throw new BadRequestException("Cursor signature invalid", "invalid_cursor");
			}

			$payload = json_decode(Base64Url::decode($body), true);

			if (!is_array($payload)) {
				throw new BadRequestException("Cursor payload invalid", "invalid_cursor");
			}

			return $payload;
		}

		public static function cursor(Request $request, $secret, $max_per_page = self::MAX_PER_PAGE) {
			$per_page = max(1, min($max_per_page, (int)($request->query["per_page"] ?? self::DEFAULT_PER_PAGE)));
			$cursor_param = $request->query["cursor"] ?? null;
			$cursor_data = $cursor_param ? self::decodeCursor($cursor_param, $secret) : null;

			return ["per_page" => $per_page, "cursor" => $cursor_data];
		}
	}
