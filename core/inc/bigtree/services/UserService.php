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
	use BigTree\Services\AI\Tools\UserToolBackend;
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
	class UserService implements UserToolBackend {
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

		// — AI tool seam (UserToolBackend) —
		//
		// Intentionally narrow: administrators can create basic editor accounts and
		// edit profile fields. Level, permissions, and passwords are never touched by
		// the assistant (privilege changes are an explicit non-tool), a new account is
		// always level 0, and a user outranking the actor can't be edited. Every guard
		// is re-checked at approval, never trusted from the model or the stored payload.

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateUserCreate(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can create users."];
			}

			$email = trim((string)($args["email"] ?? ""));

			if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

				return ["error" => "A valid email address is required."];
			}

			if (SQL::exists("bigtree_users", ["email" => $email])) {

				return ["error" => "A user with the email {$email} already exists."];
			}

			$name = trim((string)($args["name"] ?? ""));
			$company = trim((string)($args["company"] ?? ""));
			$timezone = trim((string)($args["timezone"] ?? ""));

			return [
				"ok" => true,
				"summary" => "Create a new editor account for " . ($name !== "" ? "{$name} ({$email})" : $email)
					. ". They'll be an editor (level 0) and will need a password set separately.",
				"preview" => [
					"action" => "create_user",
					"email" => $email,
					"name" => $name,
					"company" => $company,
					"timezone" => $timezone,
					"level" => 0,
				],
				"payload" => [
					"email" => $email,
					"name" => $name,
					"company" => $company,
					"timezone" => $timezone,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateUser(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can create users.");
			}

			$email = trim((string)($payload["email"] ?? ""));

			if ($email === "" || SQL::exists("bigtree_users", ["email" => $email])) {

				return ["mode" => "error", "message" => "That email address is no longer available."];
			}

			// Always level 0, no password, no permissions — the assistant never grants
			// privileges. A password is set out of band (reset flow / admin UI).
			$id = (int)SQL::insert("bigtree_users", [
				"email" => BigTree::safeEncode($email),
				"level" => 0,
				"name" => BigTree::safeEncode((string)($payload["name"] ?? "")),
				"company" => BigTree::safeEncode((string)($payload["company"] ?? "")),
				"daily_digest" => "",
				"alerts" => [],
				"permissions" => [],
				"timezone" => (string)($payload["timezone"] ?? ""),
			]);

			return [
				"mode" => "created",
				"user_id" => $id,
				"email" => $email,
				"note" => "The account was created at editor level with no password; set one via the users screen.",
			];
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateUserUpdate(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can edit users."];
			}

			$id = (int)($args["user_id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A user_id is required."];
			}

			$target = SQL::fetch("SELECT id, email, name, company, level, timezone FROM bigtree_users WHERE id = ?", $id);

			if (!$target) {

				return ["error" => "User {$id} does not exist."];
			}

			if ((int)$target["level"] > PermissionService::level($user)) {

				return ["denied" => "You cannot edit a user whose level is higher than yours."];
			}

			$changes = [];
			$diff = [];

			foreach (["name", "company", "timezone"] as $field) {
				if (!array_key_exists($field, $args)) {

					continue;
				}

				$new = trim((string)$args[$field]);

				if ($new !== (string)($target[$field] ?? "")) {
					$changes[$field] = $new;
					$diff[$field] = ["from" => (string)($target[$field] ?? ""), "to" => $new];
				}
			}

			if (array_key_exists("email", $args)) {
				$email = trim((string)$args["email"]);

				if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

					return ["error" => "The email address is not valid."];
				}

				if ($email !== (string)$target["email"]) {
					// SQL::exists can't express "another row", so check for a clash directly
					// (matches the approval-time re-check in aiUpdateUser).
					if (SQL::fetchSingle("SELECT id FROM bigtree_users WHERE email = ? AND id != ?", $email, $id)) {

						return ["error" => "Another user already uses the email {$email}."];
					}

					$changes["email"] = $email;
					$diff["email"] = ["from" => (string)$target["email"], "to" => $email];
				}
			}

			if (!$changes) {

				return ["error" => "No profile changes were supplied — nothing to update."];
			}

			$label = trim((string)$target["name"]) ?: (string)$target["email"];

			return [
				"ok" => true,
				"summary" => "Update the profile for {$label}. (Level and permissions are never changed by the assistant.)",
				"preview" => [
					"action" => "update_user",
					"user_id" => $id,
					"user_label" => $label,
					"changes" => $diff,
				],
				"payload" => [
					"user_id" => $id,
					"changes" => $changes,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdateUser(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can edit users.");
			}

			$id = (int)($payload["user_id"] ?? 0);
			$target = $id > 0 ? SQL::fetch("SELECT id, level FROM bigtree_users WHERE id = ?", $id) : null;

			if (!$target) {

				return ["mode" => "error", "message" => "That user no longer exists."];
			}

			if ((int)$target["level"] > PermissionService::level($user)) {
				throw new AuthorizationException("Cannot modify a user with a higher level");
			}

			$changes = is_array($payload["changes"] ?? null) ? $payload["changes"] : [];

			// Re-validate a staged email at approval time — another account may have
			// claimed it during the proposal's TTL. Never trust the guard the staging
			// pass made; the create path does the same.
			if (array_key_exists("email", $changes)) {
				$email = trim((string)$changes["email"]);

				if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

					return ["mode" => "error", "message" => "That email address is not valid."];
				}

				// SQL::exists can't express "another row" (its 3rd arg is an ignored id,
				// not a bound param), so check for a clashing row directly.
				if (SQL::fetchSingle("SELECT id FROM bigtree_users WHERE email = ? AND id != ?", $email, $id)) {

					return ["mode" => "error", "message" => "That email address is no longer available."];
				}
			}

			$update = [];

			foreach (["name", "company", "email"] as $field) {
				if (array_key_exists($field, $changes)) {
					$update[$field] = BigTree::safeEncode((string)$changes[$field]);
				}
			}

			if (array_key_exists("timezone", $changes)) {
				$update["timezone"] = (string)$changes["timezone"];
			}

			// The allow-list above is the guard: level and permissions are never
			// copied into $update, so they can't be written through this path.
			if ($update) {
				SQL::update("bigtree_users", $id, $update);
			}

			return [
				"mode" => "updated",
				"user_id" => $id,
			];
		}
	}
