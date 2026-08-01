<?php
	/**
	 * Audit #14 guard E3, and the behaviour of B1/B2/C4: the caller.
	 *
	 * The other half of audit #14's subject. Like the clock, who the user *is* is a
	 * request-time fact rather than something in the codebase — and the consequence was
	 * the same shape: not a crash but a wrong answer. The assistant knew the caller's
	 * role and not their identity, and it required level 1 for a profile edit that
	 * `PATCH /users/{id}` has always allowed a user to make to their own record. So "turn
	 * off my daily digest" told an editor to ask an administrator for two clicks they
	 * could do themselves, with no decline line to explain the wall because there wasn't
	 * one — the AI surface stricter than REST, undeliberately (the class audit #13 D4
	 * closed for callout reads).
	 *
	 * E3 is the structural leg: any route whose permission is
	 * `["any" => [["level" => N], ["self" => …]]]` is a route the CMS lets a user use on
	 * themselves at any level, so the assistant must either offer a tool an editor can
	 * reach or declare the wall. The routes are derived from the route files, so the next
	 * self-scoped endpoint to land has to be classified rather than quietly diverging.
	 */

	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AIChatService;
	use BigTree\Services\UserService;

	/**
	 * How the assistant covers each route the CMS scopes to "self".
	 *
	 * The value is either a tool that an **editor** can reach — a level-1 tool is no
	 * answer to a level-0 capability — or `declined: <phrase>`, where the phrase must
	 * appear in an outOfScope() key.
	 *
	 * @return array<string,string> route => tool name | "declined: phrase"
	 */
	function ai_caller_self_route_coverage(): array {

		return [
			// Reading your own record. search_users is administrator-gated (audit #1), so
			// before audit #14 B2 an editor could not look themselves up at all — and the
			// capability payload, which every user can call, carried no identity.
			"GET /users/{id:int}" => "get_my_capabilities",
			// Audit #14 B1. The Profile screen in the SPA is this route.
			"PATCH /users/{id:int}" => "update_user",
			// Authentication credentials are a category the assistant never touches, at
			// any level, for anybody — including the caller themselves.
			"POST /users/{id:int}/password" => "declined: levels, permissions or passwords",
		];
	}

	/**
	 * Routes the CMS lets a user act on their own record for, read out of the route
	 * files' permission blocks rather than listed here.
	 *
	 * @return list<string>
	 */
	function ai_caller_self_scoped_routes(): array {
		$routes = [];

		foreach (glob(SERVER_ROOT . "core/inc/bigtree/api/routes/*.php") ?: [] as $file) {
			preg_match_all(
				'/"((?:GET|POST|PUT|PATCH|DELETE) [^"]+)"\s*=>/',
				(string)file_get_contents($file),
				$matches
			);

			foreach ($matches[1] as $route) {
				// Read from the route's own block, bounded at the next declaration, so a
				// self clause can't be attributed to the route above it.
				$block = ai_inverse_route_block((string)$route);

				if ($block !== "" && strpos($block, '"self" =>') !== false) {
					$routes[(string)$route] = true;
				}
			}
		}

		return array_keys($routes);
	}

	/**
	 * E3: every self-scoped route is reachable by an editor through a tool, or declared
	 * as a wall — and the coverage map doesn't outlive the routes.
	 */
	function test_every_self_scoped_route_has_an_editor_tool_or_a_decline() {
		$routes = ai_caller_self_scoped_routes();
		$coverage = ai_caller_self_route_coverage();
		$registry = ai_wiring_registry();
		$editor = ai_wiring_user(0);
		$declines = strtolower(implode(" | ", array_keys(CapabilitySummary::outOfScope())));

		T::ok(count($routes) > 0, "the self-scoped routes were found (" . implode(", ", $routes) . ")");

		$unclassified = [];

		foreach ($routes as $route) {
			if (!isset($coverage[$route])) {
				$unclassified[] = $route;

				continue;
			}

			$named = $coverage[$route];

			if (strpos($named, "declined:") === 0) {
				$phrase = strtolower(trim(substr($named, strlen("declined:"))));
				T::ok(
					strpos($declines, $phrase) !== false,
					"{$route} is declined by wording matching \"{$phrase}\""
				);

				continue;
			}

			$tool = $registry->get($named);
			T::ok($tool !== null, "{$route} is covered by the registered tool {$named}");

			// The whole point of the leg: a tool the CMS's own permission rule would let
			// an editor use, that the assistant hides from editors, is the finding.
			T::ok(
				$tool !== null && $tool->isAvailable($editor),
				"{$named} is offered to an editor, as the route's own self clause is"
			);
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every route the CMS scopes to self has an editor-reachable tool or a named decline"
		);

		$stale = array_values(array_diff(array_keys($coverage), $routes));
		T::equals(implode(", ", $stale), "", "no coverage entry names a route that is no longer self-scoped");
	}

	/**
	 * B2: the capability payload identifies the caller. Every consumer of "me" — which
	 * drafts are mine, what am I subscribed to, sign it with my name — resolves through
	 * this, and it carried level and role and nothing else.
	 */
	function test_the_capability_payload_identifies_the_caller() {
		$user = (object)[
			"id" => 42,
			"level" => 0,
			"permissions" => [],
			"name" => "Ada Lovelace",
			"email" => "ada@example.com",
			"timezone" => "Europe/London",
		];
		$caps = CapabilitySummary::forUser($user);

		T::equals($caps["user_id"], 42, "the payload carries the caller's id");
		T::equals($caps["name"], "Ada Lovelace", "and their name");
		T::equals($caps["email"], "ada@example.com", "and their email");
		T::equals($caps["timezone"], "Europe/London", "and their timezone");
		T::equals($caps["can_edit_own_profile"], true, "and says a profile edit is theirs to make");

		// The same seams accept an array-shaped user throughout the AI stack.
		$as_array = CapabilitySummary::forUser([
			"id" => 7, "level" => 2, "name" => "Grace", "email" => "grace@example.com",
		]);
		T::equals($as_array["user_id"], 7, "an array-shaped user resolves the same way");
		T::equals(
			CapabilitySummary::userLabel(["name" => "Grace", "email" => "grace@example.com"]),
			"Grace",
			"the label prefers the name"
		);
		T::equals(
			CapabilitySummary::userLabel(["email" => "only@example.com"]),
			"only@example.com",
			"and falls back to the email when there is no name"
		);
	}

	/**
	 * B2, prompt side: the model is told who it is talking to, and B1: it is told the
	 * self-service path exists — the two things that turned "turn off my daily digest"
	 * into a referral to an administrator.
	 */
	function test_the_chat_prompt_names_the_caller_and_the_self_service_path() {
		$service = new AIChatService();
		$editor = (object)["id" => 42, "level" => 0, "permissions" => [], "name" => "Ada Lovelace"];
		$prompt = $service->systemPrompt($editor);

		T::ok(strpos($prompt, "Ada Lovelace") !== false, "the prompt names the signed-in user");
		T::ok(strpos($prompt, "user id 42") !== false, "and gives the model their id to pass as an argument");
		T::ok(
			stripos($prompt, "edit their OWN profile") !== false,
			"and states that a user may edit their own profile at any level"
		);
		T::ok(
			stripos($prompt, "manage OTHER users") !== false,
			"and the editor's wall is about other users rather than swallowing their own record"
		);

		// The email wall is declared rather than discovered, per audit #14 D3.
		$declines = implode(" ", CapabilitySummary::outOfScopeLines());
		T::ok(
			stripos($declines, "your own email address") !== false,
			"changing your own email address is a declared wall"
		);
		T::ok(
			stripos($declines, "emulating, another user") !== false,
			"and so is emulating another user (audit #14 C3)"
		);
	}

	/**
	 * B2, read side: get_my_capabilities carries the caller's own profile, so every
	 * field update_user lets an editor write has a read an editor can reach.
	 *
	 * Two of them — `company` and `daily_digest` — are not on the request user object at
	 * all (the JWT middleware selects id, email, name, level, permissions, timezone), so
	 * they come off the row. Without this they became write-only the moment B1 made them
	 * editor-writable, which is the exact shape AISurfaceGuardTest exists to refuse.
	 */
	function test_the_capabilities_tool_reads_back_every_self_writable_field() {
		if (!parity_db_available()) {

			return;
		}

		$id = parity_seed_user([
			"level" => 0,
			"name" => "ZZ Audit14 Reader",
			"company" => "ZZ Audit14 Co",
			"timezone" => "Europe/Paris",
			"daily_digest" => "on",
		]);

		try {
			$profile = (new UserService())->aiMyProfile((object)["id" => $id, "level" => 0, "permissions" => []]);

			T::equals($profile["user_id"], $id, "the profile read names the caller");
			T::equals($profile["name"], "ZZ Audit14 Reader", "and returns their name");
			T::equals($profile["company"], "ZZ Audit14 Co", "and their company");
			T::equals($profile["timezone"], "Europe/Paris", "and their timezone");
			T::equals($profile["daily_digest"], true, "and whether the digest is on");

			// Every self-writable update_user argument is present, so the write has a read.
			$writable = ["name", "company", "timezone", "daily_digest"];

			foreach ($writable as $field) {
				T::ok(array_key_exists($field, $profile), "get_my_capabilities reads back {$field}");
			}

			T::equals(
				(new UserService())->aiMyProfile((object)["id" => 0, "level" => 0]),
				[],
				"and a caller with no id reads back nothing rather than someone else's record"
			);
		} finally {
			parity_delete_users($id);
		}
	}

	/** B1: update_user reaches an editor's registry now, because their own record is theirs. */
	function test_update_user_is_offered_at_every_level() {
		$registry = ai_wiring_registry();
		$tool = $registry->get("update_user");

		T::ok($tool !== null, "update_user is registered");
		T::ok($tool !== null && $tool->isAvailable(ai_wiring_user(0)), "and offered to an editor");
		T::ok($tool !== null && $tool->isAvailable(ai_wiring_user(1)), "and to an administrator");
	}

	/**
	 * B1, behaviour: an editor may stage an edit to their own profile, and to nobody
	 * else's. The denial names their own id rather than just refusing, because the model
	 * reaching for someone else's record is usually the model not knowing which id is
	 * "me".
	 */
	function test_an_editor_edits_their_own_profile_and_no_one_elses() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new UserService();
		$id = parity_seed_user(["level" => 0, "name" => "ZZ Audit14 Self"]);
		$other = parity_seed_user(["level" => 0, "name" => "ZZ Audit14 Other"]);

		try {
			$editor = (object)["id" => $id, "level" => 0, "permissions" => []];

			$staged = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"daily_digest" => true,
				"timezone" => "Europe/London",
			], $editor);

			T::ok(!empty($staged["ok"]), "an editor's own notification preferences validate");

			$result = $svc->aiUpdateUser($staged["payload"], $editor);
			T::equals((string)$result["mode"], "updated", "and the approval applies them");

			$row = SQL::fetch("SELECT daily_digest, timezone FROM bigtree_users WHERE id = ?", $id);
			T::equals((string)$row["daily_digest"], "on", "the digest flag was written");
			T::equals((string)$row["timezone"], "Europe/London", "and the timezone");

			// Someone else's record is still administrator-only, and the refusal is
			// actionable rather than a flat wall.
			$denied = $svc->aiValidateUserUpdate(["user_id" => $other, "name" => "Nope"], $editor);
			T::ok(isset($denied["denied"]), "another user's profile is denied to an editor");
			T::ok(
				strpos((string)$denied["denied"], (string)$id) !== false,
				"and the denial names the id that would have worked"
			);

			// Level and permissions are unconditional exclusions, self or not.
			$staged_level = $svc->aiValidateUserUpdate(["user_id" => $id, "name" => "ZZ Audit14 Renamed"], $editor);
			T::ok(!empty($staged_level["ok"]), "a self rename validates");
			T::ok(
				!array_key_exists("level", $staged_level["payload"]["changes"])
					&& !array_key_exists("permissions", $staged_level["payload"]["changes"]),
				"and stages no level or permissions change"
			);
		} finally {
			parity_delete_users($id, $other);
		}
	}

	/**
	 * D3: the one wall that stays up on the self path. REST allows a self email change;
	 * the assistant does not, because an email address is the sign-in identity and a chat
	 * card is one click. Re-asked at approval for the same reason every other staged
	 * value is.
	 */
	function test_a_self_edit_never_changes_the_email_address() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new UserService();
		$id = parity_seed_user(["level" => 1, "name" => "ZZ Audit14 Admin"]);
		$target = parity_seed_user(["level" => 0, "name" => "ZZ Audit14 Target"]);

		try {
			$admin = (object)["id" => $id, "level" => 1, "permissions" => []];

			$denied = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"email" => "zz_audit14_new@test.local",
			], $admin);
			T::ok(isset($denied["denied"]), "changing your own email is refused even at administrator level");
			T::ok(
				stripos((string)$denied["denied"], "sign in") !== false,
				"and the refusal says why rather than citing permission"
			);

			// Echoing back the address that is already stored is not a change.
			$stored = (string)SQL::fetchSingle("SELECT email FROM bigtree_users WHERE id = ?", $id);
			$noop = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"email" => $stored,
				"company" => "ZZ Audit14 Co",
			], $admin);
			T::ok(!empty($noop["ok"]), "restating the current address alongside a real change is not refused");

			// Somebody else's email is an ordinary administrator edit.
			$other = $svc->aiValidateUserUpdate([
				"user_id" => $target,
				"email" => "zz_audit14_other@test.local",
			], $admin);
			T::ok(!empty($other["ok"]), "an administrator may still change another user's email");

			// And the approval re-asks: the payload sits for 24h, and a demotion inside
			// that window puts an administrator's card on the self path.
			$replayed = $svc->aiUpdateUser(["user_id" => $id, "changes" => ["email" => "zz_audit14_sneak@test.local"]], $admin);
			T::equals((string)$replayed["mode"], "error", "a self email change is refused at approval too");
			T::equals(
				(string)SQL::fetchSingle("SELECT email FROM bigtree_users WHERE id = ?", $id),
				$stored,
				"and nothing was written"
			);
		} finally {
			parity_delete_users($id, $target);
		}
	}

	/**
	 * C4: an alert subscription needs view access to the page.
	 *
	 * Existence was the only check, which was harmless while update_user was
	 * administrator-only and stopped being harmless the moment an editor could edit their
	 * own alerts: the digest names every watched page by title, so a page id the model
	 * guessed at would mail an editor the title of a page they cannot open.
	 */
	function test_an_alert_subscription_needs_view_access_to_the_page() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new UserService();
		$id = parity_seed_user(["level" => 0, "name" => "ZZ Audit14 Alerts"]);
		$page_id = parity_seed_page(["nav_title" => "ZZ Audit14 Unreadable"]);

		try {
			$editor = (object)["id" => $id, "level" => 0, "permissions" => []];

			$refused = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"alerts" => [(string)$page_id => true],
			], $editor);
			T::ok(isset($refused["error"]), "watching a page the caller can't view is refused");

			// The whole-tree key is not a page and has nothing to check.
			$whole_tree = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"alerts" => ["0" => true],
			], $editor);
			T::ok(!empty($whole_tree["ok"]), "the whole-tree subscription (page id 0) still validates");

			// With view access it goes through.
			$granted = (object)[
				"id" => $id,
				"level" => 0,
				"permissions" => ["page" => [(string)$page_id => "e"]],
			];
			$allowed = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"alerts" => [(string)$page_id => true],
			], $granted);
			T::ok(!empty($allowed["ok"]), "and a page they do have access to validates");

			// Unsubscribing must survive access being revoked — that is exactly the
			// cleanup the check must not block.
			SQL::update("bigtree_users", $id, ["alerts" => json_encode([(string)$page_id => "on"])]);
			$removing = $svc->aiValidateUserUpdate([
				"user_id" => $id,
				"alerts" => [(string)$page_id => false],
			], $editor);
			T::ok(!empty($removing["ok"]), "unsubscribing from a page they can no longer view is allowed");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($id);
		}
	}
