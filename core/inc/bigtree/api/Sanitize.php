<?php
	namespace BigTree\Api;

	/**
	 * Shared, behavior-identical sanitizers used before security-sensitive
	 * operations (e.g. filesystem writes). Keep these canonical: never re-inline
	 * a copy of one of these checks in a service — call the helper instead, so a
	 * future hardening fix lands in exactly one place.
	 */
	class Sanitize {
		/**
		 * A single path segment is safe when it's non-empty, contains only
		 * letters / digits / dot / dash / underscore (extension ids are reverse-DNS
		 * like "com.fastspot.date-range"), and has no ".." traversal. The first
		 * character must be [a-z0-9], so leading dots (e.g. ".hidden") are rejected.
		 */
		public static function pathSegment(string $segment): bool {

			return $segment !== ""
				&& (bool)preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $segment)
				&& strpos($segment, "..") === false;
		}

		// Field title → safe MySQL column name (urlify, hyphens→underscores, strip the
		// rest). Mirrors form-create.php's `$cms->urlify` + str_replace.
		public static function columnName(string $title): string {
			$name = \BigTreeCMS::urlify($title);
			$name = str_replace(["`", "-"], ["", "_"], $name);

			return preg_replace('/[^A-Za-z0-9_]/', "", $name);
		}

		/**
		 * Build a `LIKE` search term: escape the wildcard metacharacters in the
		 * user's query and wrap it in `%…%` for a "contains" match. The escape
		 * char itself (`\`) is doubled first, then `%` and `_` are neutralized so
		 * they match literally rather than as wildcards — callers rely on MySQL's
		 * default backslash escaping (no explicit `ESCAPE` clause needed).
		 *
		 * Pass $lowercase = true when matching against a column whose values are
		 * stored lowercased (e.g. tags) so case-sensitive collations still match.
		 */
		public static function likeTerm(string $q, bool $lowercase = false): string {
			$escaped = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $q);

			if ($lowercase) {
				$escaped = strtolower($escaped);
			}

			return "%" . $escaped . "%";
		}

		// Validate a sort clause against an allow-list of known columns so we never
		// concatenate user-controlled text into SQL. Returns a backticked
		// ``\`col\` DIR`` for an allowed column, otherwise ``\`fallback\` ASC``.
		public static function orderClause(string $raw, array $columns, string $fallback): string {
			$trimmed = trim($raw);

			if ($trimmed !== "") {
				if (preg_match('/^`?([A-Za-z0-9_-]+)`?(?:\s+(ASC|DESC))?\s*$/i', $trimmed, $m)) {
					$col = $m[1];
					$dir = strtoupper($m[2] ?? "ASC");

					if (!empty($columns[$col])) {
						return "`$col` $dir";
					}
				}
			}

			return "`$fallback` ASC";
		}
	}
