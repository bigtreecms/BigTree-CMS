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

		/**
		 * Validate a record id supplied as request input: non-empty, no longer than
		 * $max characters, and composed only of alphanumerics plus the $extra
		 * characters (dash + underscore by default; pass "." too for reverse-DNS
		 * extension ids). Folds the hand-rolled
		 * `ctype_alnum(str_replace([...], "", $id))` checks the JSONDB resource
		 * services otherwise repeat with subtly inconsistent caps.
		 */
		public static function isValidId(string $id, int $max = 127, string $extra = "-_"): bool {
			if ($id === "" || strlen($id) > $max) {
				return false;
			}

			$stripped = $extra === "" ? $id : str_replace(str_split($extra), "", $id);

			return $stripped !== "" && ctype_alnum($stripped);
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

		/**
		 * Presentation-side inverse of BigTree::safeEncode: decode HTML entities
		 * back to their characters for API output. Uses the same flag set the
		 * services previously hand-wrote 24× (ENT_QUOTES | ENT_HTML5, UTF-8) and
		 * casts null to "" so callers can drop the `(string)` wrappers.
		 */
		public static function decodeEntities(?string $value): string {

			return html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, "UTF-8");
		}

		/**
		 * Build an IN (…) placeholder list for a prepared statement: "?,?,?" for
		 * a three-element array. Callers keep their own emptiness guards — every
		 * site already checks the array is non-empty before building the clause.
		 *
		 * @param array $values Values that will be bound to the placeholders.
		 */
		public static function placeholders(array $values): string {

			return implode(",", array_fill(0, count($values), "?"));
		}
	}
