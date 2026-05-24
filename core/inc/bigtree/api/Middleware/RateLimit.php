<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Exceptions\RateLimitException;
	use SQL;

	/**
	 * Fixed-window per-IP rate limit on /auth/* endpoints.
	 *  - Window: 60 seconds
	 *  - Limit: configurable per route via 'rate_limit' => ['per_minute' => N]
	 *  - Default for auth routes: 10/min
	 *
	 * Skipped entirely for non-auth routes in v1.
	 */
	class RateLimit {
		const WINDOW_SECONDS = 60;
		const DEFAULT_PER_MINUTE = 10;

		public function handle(Request $request, callable $next) {
			$route = $request->route;
			$applies = (strpos($request->path, "/auth/") === 0) || !empty($route["rate_limit"]);
			if (!$applies) return $next($request);

			$limit = (int)($route["rate_limit"]["per_minute"] ?? self::DEFAULT_PER_MINUTE);
			$bucket = "auth:ip:" . $request->ip . ":" . trim($request->path, "/");
			$window = date("Y-m-d H:i:00", time() - (time() % self::WINDOW_SECONDS));

			// Upsert + read in a way that doesn't need a transaction.
			SQL::query(
				"INSERT INTO bigtree_api_rate_limits (bucket, window_start, count) VALUES (?, ?, 1) " .
				"ON DUPLICATE KEY UPDATE count = count + 1",
				$bucket, $window
			);
			$row = SQL::fetch("SELECT count FROM bigtree_api_rate_limits WHERE bucket = ? AND window_start = ?", $bucket, $window);
			$count = (int)($row["count"] ?? 1);

			if ($count > $limit) {
				$retry_after = self::WINDOW_SECONDS - (time() % self::WINDOW_SECONDS);
				throw new RateLimitException($retry_after);
			}

			return $next($request);
		}
	}
