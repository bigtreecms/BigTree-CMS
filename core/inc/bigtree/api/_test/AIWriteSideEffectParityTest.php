<?php
	/**
	 * Audit #19 guard E1: the side effects a write is supposed to have.
	 *
	 * Every earlier AI guard relates a *tool* to a *backend seam*, or a route body
	 * field to a tool argument. None of them can see a side effect that fires from
	 * the REST **request handler** rather than from the shared write primitive the AI
	 * seam also calls — so when module form hooks were added to
	 * AutoModuleService::create()/update(), the five AI entry write sites inherited
	 * nothing, and nothing failed.
	 *
	 * The positive control is one service over: page template hooks fire from inside
	 * performCreate()/performUpdate(), the primitives the AI path already goes
	 * through, so they have always fired on the AI path for free. That is the shape
	 * to copy — and this is the guard for when it isn't copied.
	 *
	 * Two legs, deliberately different in kind:
	 *
	 *   1. Static — the two writers of a module entry call the same declared set of
	 *      side-effect helpers in their own bodies, or the difference carries a
	 *      written reason. Direct bodies only: expanding helpers makes everything
	 *      look like everything, the lesson AIDestructiveDisclosureTest encodes.
	 *   2. Behavioural — a real module form with real hooks, driven through
	 *      aiCreateEntry live and pending. The pending leg is the one that proves
	 *      the stored-publish_hook half: a change queued with NULL loses its hook
	 *      permanently, whoever approves it later.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PendingChangeService;

	// — leg 1: static parity between the two writers of a module entry —

	/**
	 * The side effects a module-entry write is supposed to have, each named by the
	 * token that appears in a write body when it runs.
	 *
	 * @return array<string,string> token => what it does
	 */
	function ai_write_side_effects(): array {

		return [
			"fireFormHooks" => "the module form's post and publish hooks",
			"formPublishHook" => "the publish hook stored on a queued change, so approval can fire it later",
			"trackModuleResources" => "resource allocation from the data being written",
			"trackLiveEntryResources" => "resource allocation re-scanned from the live row",
			"applyFormFieldDefaults" => "the form's per-field default values",
			"applyFormDefaultPosition" => "the form's default sort position",
			"BigTreeAutoModule::sanitizeData" => "value normalization before the write",
			"Hooks::fire" => "the module_entry.* event other services subscribe to",
		];
	}

	/**
	 * The writer pairs. Each is one table written two ways: the REST request handler
	 * and the AI approval seam.
	 *
	 * @return array<string,array{rest:array{0:string,1:string},ai:array{0:string,1:string}}>
	 */
	function ai_write_side_effect_pairs(): array {

		return [
			"module entry create" => [
				"rest" => [AutoModuleService::class, "create"],
				"ai" => [AutoModuleService::class, "aiCreateEntry"],
			],
			"module entry update" => [
				"rest" => [AutoModuleService::class, "update"],
				"ai" => [AutoModuleService::class, "aiUpdateEntry"],
			],
		];
	}

	/**
	 * Differences between a pair's two writers that are correct, with the reason.
	 *
	 * An entry here is a claim that the side effect still happens on both paths, just
	 * not from both bodies — never that one path is allowed to skip it. Anything with
	 * no entry fails, which is what makes adding a side effect to one writer alone
	 * loud instead of silent.
	 *
	 * @return array<string,array<string,string>> pair => [token => reason]
	 */
	function ai_write_side_effect_exemptions(): array {
		$prep = "REST runs it inside prepareEntryWrite(), the request-body prep create() and update() share; "
			. "the AI seam has no request body to prep, so it runs the same call inline.";

		return [
			"module entry create" => ["BigTreeAutoModule::sanitizeData" => $prep],
			"module entry update" => ["BigTreeAutoModule::sanitizeData" => $prep],
		];
	}

	/** Whether a method's own body contains a call to $token. */
	function ai_write_body_calls(string $class, string $method, string $token): bool {

		return strpos(ai_surface_method_body($class, $method), $token) !== false;
	}

	/**
	 * E1: the AI seam and the REST handler run the same side effects.
	 *
	 * Fails on `fireFormHooks` and `formPublishHook` as the tree stood at 7914f5813 —
	 * the A1 defect, which every existing guard was structurally unable to see.
	 */
	function test_both_writers_of_a_module_entry_run_the_same_side_effects() {
		$exemptions = ai_write_side_effect_exemptions();
		$diverged = [];

		foreach (ai_write_side_effect_pairs() as $pair => $seams) {
			foreach (array_keys(ai_write_side_effects()) as $token) {
				$rest = ai_write_body_calls($seams["rest"][0], $seams["rest"][1], $token);
				$ai = ai_write_body_calls($seams["ai"][0], $seams["ai"][1], $token);

				if ($rest === $ai || !empty($exemptions[$pair][$token])) {

					continue;
				}

				$diverged[] = "{$pair}: \"{$token}\" runs in " . ($rest ? $seams["rest"][1] : $seams["ai"][1])
					. " only";
			}
		}

		T::equals(
			implode(", ", $diverged),
			"",
			"every module-entry side effect runs on both the REST and the AI write path"
		);
	}

	/**
	 * The declared side-effect list is real: a token nothing in the service calls is
	 * a guard checking for something that no longer exists, which passes forever.
	 */
	function test_the_declared_side_effects_all_exist() {
		$source = (string)file_get_contents(SERVER_ROOT . "core/inc/bigtree/services/AutoModuleService.php");
		$missing = [];

		foreach (ai_write_side_effects() as $token => $reason) {
			if (strpos($source, $token) === false) {
				$missing[] = $token;
			}

			T::ok($reason !== "", "the \"{$token}\" side effect says what it does");
		}

		T::equals(implode(", ", $missing), "", "every declared side effect is a call AutoModuleService actually makes");
	}

	/**
	 * …and no exemption outlives the difference it excuses. An exemption for a pair
	 * that now agrees hides the next divergence in the same cell.
	 */
	function test_no_write_side_effect_exemption_is_stale() {
		$pairs = ai_write_side_effect_pairs();
		$stale = [];

		foreach (ai_write_side_effect_exemptions() as $pair => $tokens) {
			foreach ($tokens as $token => $reason) {
				if (!isset($pairs[$pair])) {
					$stale[] = "{$pair} (no such writer pair)";

					continue;
				}

				T::ok($reason !== "", "the {$pair} \"{$token}\" exemption states why the difference is correct");

				$seams = $pairs[$pair];
				$rest = ai_write_body_calls($seams["rest"][0], $seams["rest"][1], $token);
				$ai = ai_write_body_calls($seams["ai"][0], $seams["ai"][1], $token);

				if ($rest === $ai) {
					$stale[] = "{$pair}: \"{$token}\"";
				}
			}
		}

		T::equals(implode(", ", $stale), "", "no exemption names a difference that no longer exists");
	}

	// — E3: the page resource fill rule applies on every path that writes the blob —

	/**
	 * Every writer of a page's `resources` blob, and how each is required to resolve
	 * the template id it normalizes against.
	 *
	 * The fallback is the whole point: a create carries its own template because
	 * there is no stored page yet, but both edit paths receive only the keys being
	 * changed, so a change that edits `resources` without switching template has to
	 * fall back to the page's stored one. performUpdate always did; the queued path
	 * didn't, and normalized against "" (audit #19 A3).
	 *
	 * The token is the whole resolution, not just the fallback variable:
	 * `$fallback_template` on its own also matches the parameter declaration, so a
	 * body that took the fallback and then ignored it would still pass.
	 *
	 * @return array<string,array{0:string,1:string}> label => [method, required resolution]
	 */
	function ai_page_resource_writers(): array {

		return [
			"live create (performCreate)" => ["performCreate", ""],
			"live update (performUpdate)" => ["performUpdate", '$d["template"] ?? $page["template"]'],
			"queued change (pendingChangeFields)" => [
				"pendingChangeFields",
				'$this->pendingChangeTemplate($changes, $fallback_template)',
			],
		];
	}

	/** E3: all three writers normalize, and both edit paths resolve a fallback template. */
	function test_every_page_resource_writer_normalizes_against_a_resolved_template() {
		$service = \BigTree\Services\PageService::class;

		foreach (ai_page_resource_writers() as $label => [$method, $resolution]) {
			$body = ai_surface_method_body($service, $method);

			T::ok(
				strpos($body, "normalizePageResources") !== false,
				"the {$label} path fills untouched template resources"
			);

			if ($resolution === "") {

				continue;
			}

			T::ok(
				strpos($body, $resolution) !== false,
				"the {$label} path falls back to the page's stored template when the change doesn't switch it"
			);
		}

		// The queued path takes its fallback as an argument, so the caller has to
		// actually resolve one — an unpassed default would satisfy the check above
		// and normalize against "" exactly as before.
		$caller = ai_surface_method_body($service, "writePendingPageChange");
		T::ok(
			strpos($caller, "SELECT template FROM bigtree_pages") !== false,
			"and the caller resolves that fallback from the page being edited"
		);

		// Resource allocation reads the same blob against the same template and had
		// the identical defect three lines below the normalization one. Both go
		// through pendingChangeTemplate() now, so a bare `$changes["template"] ?? ""`
		// left in this body is an allocation call that skipped the fallback.
		T::ok(
			strpos($caller, "allocatePageResources") !== false,
			"the queued path allocates the change's resources"
		);
		T::ok(
			strpos($caller, '$changes["template"] ?? ""') === false,
			"and every allocation resolves its template through pendingChangeTemplate(), fallback included"
		);
	}

	// — leg 2: the hooks actually fire —

	/**
	 * Hook call log. `$op` is "add", "reset" or "read"; the entries are the shape
	 * "post:live" / "publish:{table}:{id}" so a test can assert both that a hook ran
	 * and that it ran with the right published-ness.
	 *
	 * @return list<string>
	 */
	function ai_side_effect_hook_log(string $op = "read", string $entry = ""): array {
		static $log = [];

		if ($op === "reset") {
			$log = [];
		} elseif ($op === "add") {
			$log[] = $entry;
		}

		return $log;
	}

	/** A module form's `post` hook: fires on every write, live or pending. */
	function ai_side_effect_post_hook($id, $data, $published) {
		ai_side_effect_hook_log("add", "post:" . ($published ? "live" : "pending"));
	}

	/** A module form's `publish` hook: fires only when a row lands live. */
	function ai_side_effect_publish_hook($table, $id, $data) {
		ai_side_effect_hook_log("add", "publish:{$table}:{$id}");
	}

	/**
	 * Point every News form's hooks at the counters above, the way a developer
	 * configures them in the Module Designer. Returns the restore closure.
	 */
	function ai_side_effect_enable_form_hooks(): callable {
		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$patched = $original;

		foreach ($patched["forms"] as $key => $form) {
			$patched["forms"][$key]["hooks"] = [
				"pre" => "",
				"post" => "ai_side_effect_post_hook",
				"publish" => "ai_side_effect_publish_hook",
			];
		}

		BigTreeJSONDB::update("modules", $module_id, $patched);

		return function () use ($module_id, $original): void {
			BigTreeJSONDB::update("modules", $module_id, $original);
		};
	}

	/**
	 * Give the News form's `blurb` field a `default`, the way a developer does in the
	 * Module Designer. Returns the restore closure.
	 */
	function ai_side_effect_seed_field_default(string $value): callable {
		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$patched = $original;

		foreach ($patched["forms"] as $form_key => $form) {
			foreach (($form["fields"] ?? []) as $field_key => $field) {
				if ((string)($field["column"] ?? "") === "blurb") {
					$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
					$settings["default"] = $value;
					$patched["forms"][$form_key]["fields"][$field_key]["settings"] = $settings;
				}
			}
		}

		BigTreeJSONDB::update("modules", $module_id, $patched);

		return function () use ($module_id, $original): void {
			BigTreeJSONDB::update("modules", $module_id, $original);
		};
	}

	/**
	 * B2: an AI-created entry seeds the columns it didn't supply from their fields'
	 * `default` settings, the way the SPA's FormRenderer seeds a human's form.
	 */
	function test_an_ai_created_entry_seeds_its_untouched_columns_from_field_defaults() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_side_effect_seed_field_default("Default blurb copy");
		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;

		try {
			$result = ai_side_effect_create($svc, $dev, "zz AI Default " . bin2hex(random_bytes(3)));
			$entry_id = (int)($result["entry_id"] ?? 0);

			T::equals((string)($result["mode"] ?? ""), "published", "the create lands live");
			T::equals(
				(string)SQL::fetchSingle("SELECT blurb FROM timber_news WHERE id = ?", $entry_id),
				"Default blurb copy",
				"the column the model said nothing about carries the form field's default"
			);
		} finally {
			if ($entry_id) {
				parity_delete_news_entries($entry_id);
			}

			parity_delete_users($dev_id);
			$restore();
		}
	}

	/**
	 * Audit #20 guard E3: the gate that runs before a writer agrees with what the
	 * writer is about to do.
	 *
	 * The rule is "a field the write path will fill is not reported missing by the gate
	 * that runs before it." `applyFormFieldDefaults` runs *after* validation and no gate
	 * consulted the setting, so a required field carrying a default was refused for the
	 * assistant — for a value the write path supplies a few lines later — and pre-filled
	 * and accepted for a human, because `FormRenderer` seeds every input from the same
	 * `default` before the form's required check runs (audit #20 A3). A gate that
	 * refuses what the writer then supplies isn't enforcing an invariant; it is
	 * reporting a stale view of the data.
	 *
	 * This sits next to
	 * test_an_ai_created_entry_seeds_its_untouched_columns_from_field_defaults
	 * deliberately: that test proves the writer seeds, this proves the gate agrees, and
	 * they are the two halves of one claim.
	 */
	function test_a_required_field_with_a_default_is_not_reported_missing() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_side_effect_require_blurb("Default blurb copy");
		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;

		try {
			$validated = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz AI Required Default " . bin2hex(random_bytes(3))],
			], $dev);

			T::ok(
				!empty($validated["ok"]),
				"a create omitting a required field that declares a default stages ("
					. (string)($validated["error"] ?? "") . ")"
			);

			if (empty($validated["ok"])) {

				return;
			}

			$result = $svc->aiCreateEntry($validated["payload"], $dev);
			$entry_id = (int)($result["entry_id"] ?? 0);

			T::equals((string)($result["mode"] ?? ""), "published", "and lands live");
			T::equals(
				(string)SQL::fetchSingle("SELECT blurb FROM timber_news WHERE id = ?", $entry_id),
				"Default blurb copy",
				"…with the required column non-empty, which is what the gate was protecting"
			);
		} finally {
			if ($entry_id) {
				parity_delete_news_entries($entry_id);
			}

			parity_delete_users($dev_id);
			$restore();
		}
	}

	/**
	 * …and the gate still fires when there is genuinely nothing to fill the field with.
	 * An empty or whitespace-only default seeds nothing usable and leaves the column as
	 * blank as it started, so it is not a default the gate may rely on.
	 */
	function test_a_required_field_without_a_usable_default_is_still_reported_missing() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			foreach (["no default at all" => null, "a whitespace-only default" => " "] as $label => $default) {
				$restore = ai_side_effect_require_blurb($default);

				try {
					$validated = (new AutoModuleService())->aiValidateEntryCreate([
						"module_id" => parity_news_module_id(),
						"data" => ["title" => "zz AI Required Missing " . bin2hex(random_bytes(3))],
					], $dev);

					T::ok(empty($validated["ok"]), "a required field with {$label} still blocks the create");
					T::ok(
						strpos((string)($validated["error"] ?? ""), "blurb") !== false,
						"…and the error names it"
					);
				} finally {
					$restore();
				}
			}
		} finally {
			parity_delete_users($dev_id);
		}
	}

	/**
	 * The page half of the same rule: a required template resource carrying a default is
	 * filled by normalizePageResources inside performCreate, past the gate that used to
	 * refuse it.
	 */
	function test_a_required_template_resource_with_a_default_is_not_reported_missing() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$pages = new \BigTree\Services\PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$template = "zz_required_default_" . bin2hex(random_bytes(4));
		$page_id = 0;

		BigTreeJSONDB::insert("templates", [
			"id" => $template,
			"name" => "ZZ Required Default",
			"routed" => "",
			"level" => 0,
			"module" => "",
			"resources" => [
				["id" => "headline", "type" => "text", "title" => "Headline", "settings" => ["validation" => "required"]],
				["id" => "blurb", "type" => "text", "title" => "Blurb", "settings" => [
					"validation" => "required",
					"default" => "Standard blurb",
				]],
			],
		]);

		try {
			$validated = $pages->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Required Default " . bin2hex(random_bytes(3)),
				"template" => $template,
				"content" => ["headline" => "Set by the assistant"],
			], $dev);

			T::ok(
				!empty($validated["ok"]),
				"a page create omitting a required resource that declares a default stages ("
					. (string)($validated["error"] ?? "") . ")"
			);

			if (empty($validated["ok"])) {

				return;
			}

			$created = $pages->aiCreatePage($validated["payload"], $dev);
			$page_id = (int)($created["page_id"] ?? ($created["id"] ?? 0));

			T::ok($page_id > 0, "and the page is created");

			$resources = json_decode(
				(string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id),
				true
			);
			T::equals(
				(string)($resources["blurb"] ?? ""),
				"Standard blurb",
				"…with the required resource filled from its default, which is what the gate was protecting"
			);
		} finally {
			if ($page_id) {
				SQL::query("DELETE FROM bigtree_pages WHERE id = ?", $page_id);
			}

			BigTreeJSONDB::delete("templates", $template);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * Make the News form's `blurb` field required, optionally with a `default`. Pass null
	 * to require it with no default at all. Returns the restore closure.
	 */
	function ai_side_effect_require_blurb(?string $default): callable {
		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$patched = $original;

		foreach ($patched["forms"] as $form_key => $form) {
			foreach (($form["fields"] ?? []) as $field_key => $field) {
				if ((string)($field["column"] ?? "") !== "blurb") {

					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$settings["validation"] = "required";
				unset($settings["default"]);

				if ($default !== null) {
					$settings["default"] = $default;
				}

				$patched["forms"][$form_key]["fields"][$field_key]["settings"] = $settings;
			}
		}

		BigTreeJSONDB::update("modules", $module_id, $patched);

		return function () use ($module_id, $original): void {
			BigTreeJSONDB::update("modules", $module_id, $original);
		};
	}

	/** Stage and approve an AI entry create as $user. */
	function ai_side_effect_create(AutoModuleService $svc, $user, string $title): array {
		$validated = $svc->aiValidateEntryCreate([
			"module_id" => parity_news_module_id(),
			"data" => ["title" => $title],
		], $user);

		if (empty($validated["ok"])) {

			return ["mode" => "error"];
		}

		return $svc->aiCreateEntry($validated["payload"], $user);
	}

	/** A publisher's AI create lands live, so both hooks run. */
	function test_an_ai_published_entry_fires_the_forms_post_and_publish_hooks() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_side_effect_enable_form_hooks();
		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;

		try {
			ai_side_effect_hook_log("reset");
			$result = ai_side_effect_create($svc, $dev, "zz AI Hooks " . bin2hex(random_bytes(3)));
			$entry_id = (int)($result["entry_id"] ?? 0);

			T::equals((string)($result["mode"] ?? ""), "published", "a publisher's AI create lands live");

			$log = ai_side_effect_hook_log();
			T::ok(in_array("post:live", $log, true), "the form's post hook ran, told the row is live");
			T::ok(
				in_array("publish:timber_news:{$entry_id}", $log, true),
				"the form's publish hook ran with the new row's id"
			);
		} finally {
			if ($entry_id) {
				parity_delete_news_entries($entry_id);
			}

			parity_delete_users($dev_id);
			$restore();
			ai_side_effect_hook_log("reset");
		}
	}

	/**
	 * An editor's AI create queues, so only `post` runs now — and the publish hook
	 * has to survive on the change row, because approval is the only thing that can
	 * fire it and the stored string is the only copy anything has.
	 */
	function test_an_ai_queued_entry_keeps_its_publish_hook_until_approval() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_side_effect_enable_form_hooks();
		$svc = new AutoModuleService();
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)[
			"id" => $editor_id,
			"level" => 0,
			"permissions" => ["module" => [parity_news_module_id() => "e"]],
		];
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$change_id = 0;
		$entry_id = 0;

		try {
			ai_side_effect_hook_log("reset");
			$result = ai_side_effect_create($svc, $editor, "zz AI Hook Draft " . bin2hex(random_bytes(3)));
			$change_id = (int)($result["pending_id"] ?? 0);

			T::equals((string)($result["mode"] ?? ""), "pending", "an editor's AI create queues");
			T::ok($change_id > 0, "the queued change exists");

			$log = ai_side_effect_hook_log();
			T::ok(in_array("post:pending", $log, true), "the post hook ran, told the row is not live");
			T::equals(count(array_filter($log, function ($entry) {

				return strpos($entry, "publish:") === 0;
			})), 0, "the publish hook did not run for a draft");

			// The half of A1 that outlived the proposal: a NULL here can't be recovered
			// by the SPA, the REST approve route or publish_pending_change.
			T::equals(
				(string)SQL::fetchSingle("SELECT publish_hook FROM bigtree_pending_changes WHERE id = ?", $change_id),
				"ai_side_effect_publish_hook",
				"the form's publish hook is stored on the queued change"
			);

			// Approve it the way every approver does, and the stored hook fires.
			ai_side_effect_hook_log("reset");
			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change_id);
			$apply = new ReflectionMethod(PendingChangeService::class, "applyPendingChange");
			$apply->setAccessible(true);
			$applied = $apply->invoke(new PendingChangeService(), $row, $dev);
			$entry_id = (int)($applied["item_id"] ?? 0);
			$change_id = 0;

			T::ok($entry_id > 0, "approving the change promotes it to a live row");
			T::ok(
				in_array("publish:timber_news:{$entry_id}", ai_side_effect_hook_log(), true),
				"and the publish hook stored at staging fires at approval"
			);
		} finally {
			if ($entry_id) {
				parity_delete_news_entries($entry_id);
			}

			if ($change_id) {
				parity_delete_pending($change_id);
			}

			parity_delete_users($dev_id, $editor_id);
			$restore();
			ai_side_effect_hook_log("reset");
		}
	}
