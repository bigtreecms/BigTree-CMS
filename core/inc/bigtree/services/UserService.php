<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Json;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\AuthorizationException;
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
			$q = $request->queryString("q");

			$where = [];
			$params = [];

			if ($q !== "") {
				$where[] = "(email LIKE ? OR name LIKE ? OR company LIKE ?)";
				$like = Sanitize::likeTerm($q);
				$params[] = $like; $params[] = $like; $params[] = $like;
			}

			$sql_where = $where ? " WHERE " . implode(" AND ", $where) : "";

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_users" . $sql_where,
				"SELECT id, email, name, company, level, daily_digest, timezone FROM bigtree_users" . $sql_where . " ORDER BY name",
				$params,
				// Closure required: private method arrays fail the `callable` type
				// check when passed across class boundaries into Pagination.
				function ($row) {
					return $this->presentList($row);
				},
				100
			);
		}

		public function get(Request $request) {
			$id = $request->id();
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
				throw new ConflictException("A user with this email already exists", "duplicate_email");
			}

			$level = max(0, min((int)$request->user->level, (int)($d["level"] ?? 0)));

			$insert = [
				"email" => BigTree::safeEncode($d["email"]),
				"level" => $level,
				"name" => BigTree::safeEncode($d["name"] ?? ""),
				"company" => BigTree::safeEncode($d["company"] ?? ""),
				"daily_digest" => Flag::checkbox($d["daily_digest"] ?? null),
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
			$id = $request->id();
			$user = $this->loadOrFail($id);
			$d = $request->body;

			if ((int)$user["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot modify a user with a higher level");
			}

			$is_self = ($id === (int)$request->user->id);

			$update = [];

			if (isset($d["email"])) {
				if (SQL::exists("bigtree_users", "email = ? AND id != ?", $d["email"], $id)) {
					throw new ConflictException("Email already in use", "duplicate_email");
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
				$update["daily_digest"] = Flag::checkbox($d["daily_digest"]);
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
			$id = $request->id();

			if ($id === (int)$request->user->id) {
				throw new BadRequestException("Cannot delete your own account", "cannot_delete_self");
			}

			$target = $this->loadOrFail($id);

			if ((int)$target["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot delete a user with a higher level");
			}

			SQL::delete("bigtree_users", $id);

			return Response::noContent();
		}

		public function password(Request $request) {
			$id = $request->id();
			$is_self = ($id === (int)$request->user->id);
			$target = $this->loadOrFail($id);

			if (!$is_self && (int)$target["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot change password for a higher-level user");
			}

			$new = $request->bodyString("new_password");
			$current = $request->bodyString("current_password", "", false);

			if ($new === "") {
				throw new BadRequestException("new_password required", "missing_password");
			}

			if (!SecurityPolicyService::validatePassword($new)) {
				throw new BadRequestException("Password does not meet policy requirements", "weak_password");
			}

			if ($is_self) {
				if ($current === "" || !password_verify($current, $target["password"])) {
					throw new AuthorizationException("Current password is incorrect", "wrong_password");
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

		/**
		 * POST /users/{id}/2fa/remove
		 * Developer action to strip a user's TOTP secret when they've lost their
		 * authenticator. Mirrors the legacy developer/security/remove-2fa.php.
		 * Refuses to act on a higher-level user.
		 */
		public function removeTwoFactor(Request $request) {
			$id = $request->id();
			$target = $this->loadOrFail($id);

			if ((int)$target["level"] > (int)$request->user->level) {
				throw new AuthorizationException("Cannot modify a higher-level user");
			}

			SQL::update("bigtree_users", $id, ["2fa_secret" => ""]);

			return Response::ok(["id" => $id, "two_factor_enabled" => false]);
		}

		// — internals —

		private function loadOrFail($id) {
			$user = Entity::findOrFail("bigtree_users", $id, "User");

			return $user;
		}

		private function presentList(array $row) {

			return [
				"id" => (int)$row["id"],
				"email" => $row["email"],
				"name" => $row["name"],
				"company" => $row["company"],
				"level" => (int)$row["level"],
				"daily_digest" => Flag::isOn($row["daily_digest"]),
				"timezone" => $row["timezone"],
			];
		}

		private function presentFull(array $row, Request $request) {
			$is_self_or_admin = ((int)$request->user->id === (int)$row["id"]) || ($request->user->level >= 1);
			$result = $this->presentList($row);
			$result["two_factor_enabled"] = !empty($row["2fa_secret"]);

			if ($is_self_or_admin) {
				$result["permissions"] = Json::decode($row["permissions"]);
				$result["alerts"] = Json::decode($row["alerts"]);
			}

			return $result;
		}
	}
