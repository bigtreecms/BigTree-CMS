<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\NotFoundException;
	use SQL;
	use BigTreeJSONDB;

	/**
	 * Loader helpers for the dominant service idiom: fetch an entity by id and
	 * throw a NotFoundException when it is missing. Centralizes the
	 *   $row = SQL::fetch(...); if (!$row) { throw new NotFoundException(...); }
	 * block that repeats across the service layer.
	 *
	 * $table / $store / $columns are always supplied as code-level string
	 * literals by callers (never request input), so the interpolation below is
	 * not an injection surface; the id is always bound as a parameter.
	 */
	class Entity {
		/**
		 * Fetch a single SQL row by primary key, or throw a 404.
		 *
		 * @param string $table   Table name (literal).
		 * @param mixed  $id      Primary key value (bound as a parameter).
		 * @param string $label   Human label for the not-found message, e.g. "Tag".
		 * @param string $columns Column list to select (literal). Defaults to "*".
		 * @return array The matched row.
		 */
		public static function findOrFail(string $table, $id, string $label, string $columns = "*"): array {

			return self::fetchOrFail("SELECT $columns FROM $table WHERE id = ?", [$id], "$label $id not found");
		}

		/**
		 * Run an arbitrary single-row SELECT and throw a 404 when it matches nothing.
		 * Covers the fetch-or-404 cases findOrFail can't express: compound keys,
		 * non-id predicates, or a custom not-found message / code.
		 *
		 * @param string $query   SQL with "?" placeholders (literal).
		 * @param array  $params  Values bound to the placeholders.
		 * @param string $message Not-found message.
		 * @param string $code    Error code string (defaults to NotFoundException's).
		 * @return array The matched row.
		 */
		public static function fetchOrFail(string $query, array $params, string $message, string $code = "resource_not_found"): array {
			$row = SQL::fetch($query, ...$params);

			if (!$row) {
				throw $code === "resource_not_found"
					? new NotFoundException($message)
					: new NotFoundException($message, $code);
			}

			return $row;
		}

		/**
		 * Fetch a single JSONDB record by id, or throw a 404.
		 *
		 * @param string $store JSONDB store name (literal), e.g. "templates".
		 * @param mixed  $id    Record id.
		 * @param string $label Human label for the not-found message, e.g. "Template".
		 * @return array The matched record.
		 */
		public static function findOrFailJson(string $store, $id, string $label): array {
			$record = BigTreeJSONDB::get($store, $id);

			if (!$record) {
				throw new NotFoundException("$label $id not found");
			}

			return $record;
		}
	}
