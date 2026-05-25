<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTreeAdmin;
	use BigTree;
	use SQL;

	/**
	 * User management. CRUD + /me + password change + emulation.
	 *
	 * Permission gating is done by the Permission middleware (route metadata).
	 * This service contains the cross-user invariants:
	 *  - cannot create/edit/delete a user whose level exceeds your own
	 *  - cannot delete yourself
	 *  - self-update cannot raise your own level
	 */
	class UserService {
		public function list(Request $request) {
			$p = Pagination::offset($request, 100);
			$q = trim((string)($request->query["q"] ?? ""));

			$where = [];
			$params = [];

			if ($q !== "") {
				$where[] = "(email LIKE ? OR name LIKE ? OR company LIKE ?)";
				$like = "%" . str_replace("%", "\\%", $q) . "%";
				$params[] = $like; $params[] = $like; $params[] = $like;
			}

			$sql_where = $where ? " WHERE " . implode(" AND ", $where) : "";

			$count_args = array_merge(["SELECT COUNT(*) FROM bigtree_users" . $sql_where], $params);
			$total = (int)SQL::fetchSingle(...$count_args);

			$list_args = array_merge([
				"SELECT id, email, name, company, level, daily_digest, timezone FROM bigtree_users" . $sql_where . " ORDER BY name LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"],
			], $params);
			$rows = SQL::fetchAll(...$list_args);

			$items = array_map([$this, "presentList"], $rows);

			return Response::ok($items, Pagination::offsetMeta($p["page"], $p["per_page"], $total));
		}

		public function get(Request $request) {
			$id = (int)$request->route_params["id"];
			$user = $this->loadOrFail($id);

			return Response::ok($this->presentFull($user, $request));
		}

		public function me(Request $request) {
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $request->user->id);

			return Response::ok($this->presentFull($user, $request));
		}

		public function create(Request $request) {
			$d = $request->body;

			if (SQL::exists("bigtree_users", ["email" => $d["email"]])) {
				throw new ConflictException("A user with this email already exists", "duplicate_email", 409);
			}

			$level = max(0, min((int)$request->user->level, (int)($d["level"] ?? 0)));

			$insert = [
				"email" => BigTree::safeEncode($d["email"]),
				"level" => $level,
				"name" => BigTree::safeEncode($d["name"] ?? ""),
				"company" => BigTree::safeEncode($d["company"] ?? ""),
				"daily_digest" => !empty($d["daily_digest"]) ? "on" : "",
				"alerts" => is_array($d["alerts"] ?? null) ? $d["alerts"] : [],
				"permissions" => is_array($d["permissions"] ?? null) ? $d["permissions"] : [],
				"timezone" => $d["timezone"] ?? "",
			];

			if (!empty($d["password"])) {
				$insert["password"] = password_hash(trim($d["password"]), PASSWORD_DEFAULT);
				$insert["new_hash"] = "on";
			}

			$id = (int)SQL::insert("bigtree_users", $insert);
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $id);

			return Response::created($this->presentFull($user, $request), null);
		}

		public function update(Request $request) {
			$id = (int)$request->route_params["id"];
			$user = $this->loadOrFail($id);
			$d = $request->body;

			if ((int)$user["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot modify a user with a higher level", "permission_denied", 403);
			}

			$is_self = ($id === (int)$request->user->id);

			$update = [];

			if (isset($d["email"])) {
				if (SQL::exists("bigtree_users", "email = ? AND id != ?", $d["email"], $id)) {
					throw new ConflictException("Email already in use", "duplicate_email", 409);
				}

				$update["email"] = BigTree::safeEncode($d["email"]);
			}

			if (isset($d["name"])) {
				$update["name"] = BigTree::safeEncode($d["name"]);
			}

			if (isset($d["company"])) {
				$update["company"] = BigTree::safeEncode($d["company"]);
			}

			if (isset($d["timezone"])) {
				$update["timezone"] = $d["timezone"];
			}

			if (isset($d["daily_digest"])) {
				$update["daily_digest"] = !empty($d["daily_digest"]) ? "on" : "";
			}

			if (isset($d["alerts"]) && is_array($d["alerts"])) {
				$update["alerts"] = $d["alerts"];
			}

			if (!$is_self) {
				if (isset($d["level"])) {
					$update["level"] = max(0, min((int)$request->user->level, (int)$d["level"]));
				}

				if (isset($d["permissions"]) && is_array($d["permissions"])) {
					$update["permissions"] = $d["permissions"];
				}
			}

			if ($update) {
				// Bump token_version if permissions or level changed.
				$structural = (isset($update["level"]) && (int)$update["level"] !== (int)$user["level"])
					|| array_key_exists("permissions", $update);

				if ($structural) {
					SQL::query("UPDATE bigtree_users SET token_version = token_version + 1 WHERE id = ?", $id);
				}

				SQL::update("bigtree_users", $id, $update);
			}

			$fresh = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $id);

			return Response::ok($this->presentFull($fresh, $request));
		}

		public function delete(Request $request) {
			$id = (int)$request->route_params["id"];

			if ($id === (int)$request->user->id) {
				throw new BadRequestException("Cannot delete your own account", "cannot_delete_self", 400);
			}

			$target = $this->loadOrFail($id);

			if ((int)$target["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot delete a user with a higher level", "permission_denied", 403);
			}

			SQL::delete("bigtree_users", $id);

			return Response::noContent();
		}

		public function password(Request $request) {
			$id = (int)$request->route_params["id"];
			$is_self = ($id === (int)$request->user->id);
			$target = $this->loadOrFail($id);

			if (!$is_self && (int)$target["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot change password for a higher-level user", "permission_denied", 403);
			}

			$new = trim((string)($request->body["new_password"] ?? ""));
			$current = (string)($request->body["current_password"] ?? "");

			if ($new === "") {
				throw new BadRequestException("new_password required", "missing_password", 400);
			}

			if (!BigTreeAdmin::validatePassword($new)) {
				throw new BadRequestException("Password does not meet policy requirements", "weak_password", 400);
			}

			if ($is_self) {
				if ($current === "" || !password_verify($current, $target["password"])) {
					throw new AuthorizationException("Current password is incorrect", "wrong_password", 403);
				}
			}

			SQL::update("bigtree_users", $id, [
				"password" => password_hash($new, PASSWORD_DEFAULT),
				"new_hash" => "on",
			]);

			// Bump token_version: invalidate all existing tokens for this user.
			SQL::query("UPDATE bigtree_users SET token_version = token_version + 1 WHERE id = ?", $id);

			return Response::noContent();
		}

		// — internals —

		private function loadOrFail($id) {
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $id);

			if (!$user) {
				throw new NotFoundException("User $id not found", "resource_not_found", 404);
			}

			return $user;
		}

		private function presentList(array $row) {

			return [
				"id" => (int)$row["id"],
				"email" => $row["email"],
				"name" => $row["name"],
				"company" => $row["company"],
				"level" => (int)$row["level"],
				"daily_digest" => $row["daily_digest"] === "on",
				"timezone" => $row["timezone"],
			];
		}

		private function presentFull(array $row, Request $request) {
			$is_self_or_admin = ((int)$request->user->id === (int)$row["id"]) || ($request->user->level >= 1);
			$result = [
				"id" => (int)$row["id"],
				"email" => $row["email"],
				"name" => $row["name"],
				"company" => $row["company"],
				"level" => (int)$row["level"],
				"daily_digest" => $row["daily_digest"] === "on",
				"timezone" => $row["timezone"],
				"two_factor_enabled" => !empty($row["2fa_secret"]),
			];

			if ($is_self_or_admin) {
				$result["permissions"] = json_decode($row["permissions"] ?: "[]", true) ?: [];
				$result["alerts"] = json_decode($row["alerts"] ?: "[]", true) ?: [];
			}

			return $result;
		}
	}
