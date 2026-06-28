<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree;
	use BigTreeCMS;
	use SQL;

	/**
	 * Audit trail. Writes go through write(), reads through list().
	 * Uses the existing bigtree_audit_trail schema unchanged; extra request context
	 * lives in the sibling bigtree_audit_trail_context table.
	 */
	class AuditService {
		public static function write($table, $entry, $type, $user_id, array $context = []) {
			if (!$user_id || $table === "") {
				return null;
			}

			$audit_id = (int)SQL::insert("bigtree_audit_trail", [
				"table" => BigTree::safeEncode($table),
				"user" => (int)$user_id,
				"entry" => BigTree::safeEncode((string)$entry),
				"date" => "NOW()",
				"type" => BigTree::safeEncode($type),
			]);

			if ($audit_id && $context) {
				SQL::insert("bigtree_audit_trail_context", [
					"audit_id" => $audit_id,
					"ip" => substr((string)($context["ip"] ?? ""), 0, 45),
					"user_agent" => substr((string)($context["user_agent"] ?? ""), 0, 255),
					"request_id" => substr((string)($context["request_id"] ?? ""), 0, 32),
					"method" => substr((string)($context["method"] ?? ""), 0, 8),
					"path" => substr((string)($context["path"] ?? ""), 0, 255),
				]);
			}

			return $audit_id;
		}

		/**
		 * Returns the full list of database table names, sorted alphabetically.
		 * Powers the audit-trail table filter (a searchable select); the `table`
		 * column is free-form, so we expose every real table rather than only the
		 * distinct values already present in the audit log.
		 */
		public function tables(Request $request) {
			$tables = SQL::fetchAllSingle("SHOW TABLES");

			sort($tables, SORT_STRING | SORT_FLAG_CASE);

			return Response::ok(array_values($tables));
		}

		public function list(Request $request) {
			$include_context = !empty($request->query["include"]) && strpos((string)$request->query["include"], "context") !== false;

			$where = [];
			$args = [];

			if (!empty($request->query["user"])) { $where[] = "a.user = ?"; $args[] = (int)$request->query["user"]; }

			if (!empty($request->query["table"])) { $where[] = "a.`table` = ?"; $args[] = $request->query["table"]; }

			if (!empty($request->query["entry"])) { $where[] = "a.entry = ?"; $args[] = $request->query["entry"]; }

			if (!empty($request->query["start"])) { $where[] = "a.date >= ?"; $args[] = $request->query["start"]; }

			if (!empty($request->query["end"])) { $where[] = "a.date <= ?"; $args[] = $request->query["end"]; }

			$sql_where = $where ? " WHERE " . implode(" AND ", $where) : "";

			$select = $include_context
				? "SELECT a.*, u.name AS user_name, u.email AS user_email, c.ip, c.user_agent, c.request_id, c.method, c.path FROM bigtree_audit_trail a LEFT JOIN bigtree_users u ON u.id = a.user LEFT JOIN bigtree_audit_trail_context c ON c.audit_id = a.id"
				: "SELECT a.*, u.name AS user_name, u.email AS user_email FROM bigtree_audit_trail a LEFT JOIN bigtree_users u ON u.id = a.user";

			// Actors whose account was deleted no longer join to bigtree_users;
			// fall back to the cached name/email captured at deletion time.
			$deleted_users = BigTreeCMS::getSetting("bigtree-internal-deleted-users") ?: [];

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_audit_trail a" . $sql_where,
				$select . $sql_where . " ORDER BY a.date DESC, a.id DESC",
				$args,
				function ($r) use ($include_context, $deleted_users) {
					$user_name = $r["user_name"];
					$user_email = $r["user_email"];

					if ($user_name === null && isset($deleted_users[$r["user"]])) {
						$user_name = ($deleted_users[$r["user"]]["name"] ?? "") . " (deleted)";
						$user_email = $deleted_users[$r["user"]]["email"] ?? null;
					}

					$out = [
						"id" => (int)$r["id"],
						"user" => (int)$r["user"],
						"user_name" => $user_name,
						"user_email" => $user_email,
						"table" => $r["table"],
						"entry" => $r["entry"],
						"type" => $r["type"],
						"date" => $r["date"],
					];

					if ($include_context) {
						$out["context"] = [
							"ip" => $r["ip"] ?? null,
							"user_agent" => $r["user_agent"] ?? null,
							"request_id" => $r["request_id"] ?? null,
							"method" => $r["method"] ?? null,
							"path" => $r["path"] ?? null,
						];
					}

					return $out;
				},
				100
			);
		}
	}
