<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\ConflictException;
	use SQL;

	/**
	 * Concurrent-edit locks against bigtree_locks. Mirrors legacy semantics:
	 * - lock auto-refreshes if held by same user
	 * - a lock older than 5 minutes is treated as stale and may be taken over
	 * - a held lock may be forcibly taken over when `force` is set (mirrors the
	 *   legacy admin's "Unlock" button on the _locked.php interstitial)
	 */
	class LockService {
		const STALE_SECONDS = 300;

		public function acquire(Request $request) {
			$table = (string)$request->body["table"];
			$item_id = (string)$request->body["item_id"];
			$me = (int)$request->user->id;
			$force = filter_var($request->body["force"] ?? false, FILTER_VALIDATE_BOOLEAN);

			$row = SQL::fetch("SELECT * FROM bigtree_locks WHERE `table` = ? AND item_id = ?", $table, $item_id);

			if (!$force && $row && (int)$row["user"] !== $me && strtotime($row["last_accessed"]) > (time() - self::STALE_SECONDS)) {
				$holder = SQL::fetch("SELECT id, name, email FROM bigtree_users WHERE id = ?", $row["user"]);
				$conflict = new ConflictException("Locked by another user", "lock_held");
				$conflict->details = [
					"locked_by" => $holder ? ["id" => (int)$holder["id"], "name" => $holder["name"], "email" => $holder["email"]] : null,
					"last_accessed" => $row["last_accessed"],
				];
				throw $conflict;
			}

			if ($row) {
				SQL::update("bigtree_locks", $row["id"], ["last_accessed" => "NOW()", "user" => $me]);
				$lock_id = (int)$row["id"];
			} else {
				$lock_id = (int)SQL::insert("bigtree_locks", [
					"table" => $table,
					"item_id" => $item_id,
					"user" => $me,
					"title" => (string)($request->body["title"] ?? ""),
				]);
			}

			return Response::ok(["lock_id" => $lock_id, "expires_at" => date("c", time() + self::STALE_SECONDS)]);
		}

		public function refresh(Request $request) {
			$lock_id = $request->id();
			$row = SQL::fetch("SELECT * FROM bigtree_locks WHERE id = ?", $lock_id);

			if (!$row || (int)$row["user"] !== (int)$request->user->id) {
				throw new ConflictException("Lock not held by you", "lock_not_held");
			}

			SQL::update("bigtree_locks", $lock_id, ["last_accessed" => "NOW()"]);

			return Response::ok(["lock_id" => $lock_id, "expires_at" => date("c", time() + self::STALE_SECONDS)]);
		}

		public function release(Request $request) {
			$lock_id = $request->id();
			$row = SQL::fetch("SELECT * FROM bigtree_locks WHERE id = ?", $lock_id);

			if ($row && (int)$row["user"] === (int)$request->user->id) {
				SQL::delete("bigtree_locks", $lock_id);
			}

			return Response::noContent();
		}
	}
