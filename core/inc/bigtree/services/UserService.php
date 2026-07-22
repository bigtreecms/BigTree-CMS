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
		// Column caps, mirroring what routes/users.php declares. Nothing on the AI
		// path checked them, so a 900-character model-generated name was accepted
		// where both the API and the UI refuse it.
		private const AI_USER_MAX_LENGTHS = [
			"email" => 255,
			"name" => 255,
			"company" => 255,
			"timezone" => 64,
		];

		/**
		 * The first over-length user field, as a recoverable error. Shared by staging
		 * and approval so a stored payload can't slip past.
		 *
		 * @param array<string,mixed> $fields
		 */
		private function aiUserLengthError(array $fields): ?string {
			foreach (self::AI_USER_MAX_LENGTHS as $field => $max) {
				if (!array_key_exists($field, $fields)) {

					continue;
				}

				$length = mb_strlen((string)$fields[$field]);

				if ($length > $max) {

					return "The user's {$field} is {$length} characters, but the field holds at most {$max}.";
				}
			}

			return null;
		}

		/**
		 * Normalize a proposed notification preference set.
		 *
		 * `alerts` is a page-id => max-age map the dashboard's content alerts read
		 * (DashboardService::aiContentAlerts). get_content_alerts could already report
		 * a user's thresholds while nothing could set them, which made the catalog
		 * contradict itself — read-only for a preference `PATCH /users/{id}` accepts.
		 *
		 * @param mixed $alerts
		 * @return array{error?:string,alerts?:array<string,int>}
		 */
		private function aiNormalizeAlerts($alerts): array {
			if (!is_array($alerts)) {

				return ["error" => "alerts must be an object mapping page ids to a number of days."];
			}

			$out = [];

			foreach ($alerts as $page_id => $days) {
				$page_id = (int)$page_id;
				$days = (int)$days;

				if ($page_id < 0 || $days < 0) {

					return ["error" => "alerts must map real page ids to a non-negative number of days."];
				}

				$out[(string)$page_id] = $days;
			}

			return ["alerts" => $out];
		}

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

			// REST's own POST /users declares name as required, and every label
			// fallback downstream (audit rows, pending-change attribution, the users
			// list) degrades to the bare email without it — an AI-created account
			// would be the only kind with no name at all.
			if ($name === "") {

				return ["error" => "A name is required for a new user account — it's what the users list, audit "
					. "entries and pending-change attribution display."];
			}

			// An invalid tz identifier is stored happily but breaks any date-rendering
			// path that constructs a DateTimeZone from it — catch it while the model
			// can still correct itself.
			if ($timezone !== "" && !in_array($timezone, timezone_identifiers_list(), true)) {

				return ["error" => "\"{$timezone}\" is not a valid timezone identifier. Use an IANA name "
					. "like \"America/New_York\" or \"Europe/London\"."];
			}

			$too_long = $this->aiUserLengthError([
				"email" => $email,
				"name" => $name,
				"company" => $company,
				"timezone" => $timezone,
			]);

			if ($too_long !== null) {

				return ["error" => $too_long];
			}

			return [
				"ok" => true,
				"summary" => "Create a new editor account for {$name} ({$email})"
					. ". They'll be an editor (level 0) with no permissions granted. They cannot log in until a "
					. "password is set — approving this sends them an email invite to choose one.",
				"preview" => [
					"action" => "create_user",
					"email" => $email,
					"name" => $name,
					"company" => $company,
					"timezone" => $timezone,
					"level" => 0,
					"sends_invite_email" => true,
					"note" => "Cannot log in until a password is set; an invite email will be sent to {$email} on approval.",
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

			$timezone = (string)($payload["timezone"] ?? "");

			// Re-check at approval — the payload is stored between staging and
			// approval and is never trusted on the way back out.
			if ($timezone !== "" && !in_array($timezone, timezone_identifiers_list(), true)) {

				return ["mode" => "error", "message" => "\"{$timezone}\" is not a valid timezone identifier."];
			}

			$name = trim((string)($payload["name"] ?? ""));

			if ($name === "") {

				return ["mode" => "error", "message" => "A name is required for a new user account."];
			}

			$too_long = $this->aiUserLengthError([
				"email" => $email,
				"name" => $name,
				"company" => (string)($payload["company"] ?? ""),
				"timezone" => $timezone,
			]);

			if ($too_long !== null) {

				return ["mode" => "error", "message" => $too_long];
			}

			// Always level 0, no password, no permissions — the assistant never grants
			// privileges. The password is set by the invitee through the reset flow.
			$id = (int)SQL::insert("bigtree_users", [
				"email" => BigTree::safeEncode($email),
				"level" => 0,
				"name" => BigTree::safeEncode($name),
				"company" => BigTree::safeEncode((string)($payload["company"] ?? "")),
				"daily_digest" => "",
				"alerts" => [],
				"permissions" => [],
				"timezone" => $timezone,
			]);

			// Without this the account predictably sits unusable — it has no password
			// and nothing tells the person it exists. A mail failure is reported but
			// never unwinds the account that was just created.
			$invited = AuthService::sendAccountInvite($id);

			return [
				"mode" => "created",
				"user_id" => $id,
				"email" => $email,
				"invite_sent" => $invited,
				"note" => $invited
					? "The account was created at editor level with no permissions. An invite email was sent to "
						. "{$email} with a link to set a password (valid for 7 days)."
					: "The account was created at editor level with no permissions, but the invite email could not be "
						. "sent. Set a password via the users screen, or have them use \"forgot password\".",
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

			$target = SQL::fetch(
				"SELECT id, email, name, company, level, timezone, daily_digest, alerts FROM bigtree_users WHERE id = ?",
				$id
			);

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

				// Same reasoning as the create path: name is what every label
				// fallback renders, so an edit can't blank it out. (company is
				// genuinely optional and may be cleared.)
				if ($field === "name" && $new === "") {

					return ["error" => "A user's name can't be blanked — it's what the users list, audit entries and "
						. "pending-change attribution display."];
				}

				// The create path validates timezone identifiers because an invalid one
				// is stored happily and then breaks any date-rendering path that builds
				// a DateTimeZone from it. The update path has to do the same.
				if ($field === "timezone" && $new !== "" && !in_array($new, timezone_identifiers_list(), true)) {

					return ["error" => "\"{$new}\" is not a valid timezone identifier. Use an IANA name "
						. "like \"America/New_York\" or \"Europe/London\"."];
				}

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

			// Notification preferences. Both are simple typed values `PATCH /users/{id}`
			// already accepts, and get_content_alerts reads them back — so leaving them
			// unwritable made the catalog contradict itself: the assistant could report
			// a user's alert thresholds and then tell them to change it "in the admin".
			if (array_key_exists("daily_digest", $args)) {
				$digest = !empty($args["daily_digest"]) && $args["daily_digest"] !== "false";
				$current = (string)($target["daily_digest"] ?? "") !== "";

				if ($digest !== $current) {
					$changes["daily_digest"] = $digest;
					$diff["daily_digest"] = ["from" => $current ? "on" : "off", "to" => $digest ? "on" : "off"];
				}
			}

			if (array_key_exists("alerts", $args)) {
				$alerts = $this->aiNormalizeAlerts($args["alerts"]);

				if (isset($alerts["error"])) {

					return $alerts;
				}

				$current = Json::decode($target["alerts"] ?? "");

				if ($alerts["alerts"] != $current) {
					$changes["alerts"] = $alerts["alerts"];
					$diff["alerts"] = [
						"from" => count($current) . " page alert(s)",
						"to" => count($alerts["alerts"]) . " page alert(s)",
					];
				}
			}

			$too_long = $this->aiUserLengthError($changes);

			if ($too_long !== null) {

				return ["error" => $too_long];
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
				"fingerprint" => [
					"type" => "row",
					"table" => "bigtree_users",
					"id" => $id,
					"columns" => array_keys($changes),
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

			// Both re-checked at approval for the same reason the email is: the payload
			// sits for up to 24h and is never trusted on the way back out.
			if (array_key_exists("name", $changes) && trim((string)$changes["name"]) === "") {

				return ["mode" => "error", "message" => "A user's name can't be blanked."];
			}

			if (array_key_exists("timezone", $changes)) {
				$timezone = (string)$changes["timezone"];

				if ($timezone !== "" && !in_array($timezone, timezone_identifiers_list(), true)) {

					return ["mode" => "error", "message" => "\"{$timezone}\" is not a valid timezone identifier."];
				}
			}

			$too_long = $this->aiUserLengthError($changes);

			if ($too_long !== null) {

				return ["mode" => "error", "message" => $too_long];
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

			if (array_key_exists("daily_digest", $changes)) {
				$update["daily_digest"] = !empty($changes["daily_digest"]) ? "on" : "";
			}

			if (array_key_exists("alerts", $changes)) {
				$alerts = $this->aiNormalizeAlerts($changes["alerts"]);

				if (isset($alerts["error"])) {

					return ["mode" => "error", "message" => (string)$alerts["error"]];
				}

				$update["alerts"] = $alerts["alerts"];
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
