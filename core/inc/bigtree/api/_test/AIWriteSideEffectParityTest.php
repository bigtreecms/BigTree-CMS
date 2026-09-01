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
	use BigTree\Services\PageService;
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
	 * The side effects a page *move* is supposed to have.
	 *
	 * Audit #25 B1. The pair map below covered module entries only, which is exactly
	 * why this went unseen: `PageService::move` updated the row and called
	 * `repathChildren`, whose `path LIKE '$old_path/%'` match is descendants only —
	 * and which is the sole caller of `recordRouteHistory` and `reindexPage` on that
	 * path. So every descendant got a redirect and a fresh embedding while the page
	 * that actually moved got neither: its old URL 404d and its search index still
	 * held the old path. `aiMovePage` has done both since audit #11, with the reason
	 * written down, which makes this the negative of the usual finding — the AI seam
	 * was the more complete of the two.
	 *
	 * @return array<string,string> token => what it does
	 */
	function ai_page_move_side_effects(): array {

		return [
			"recordRouteHistory" => "the redirect from the moved page's own old URL, so existing links don't 404",
			"repathChildren" => "the descendants' stored paths, plus a redirect and a re-index for each",
			"reindexPage" => "the moved page's own embedding, which still holds the old path otherwise",
			"invalidateMultiSiteCache" => "the path-keyed multi-site routing map, when a trunk moves",
			"Hooks::fire" => "the page.moved event other services subscribe to",
		];
	}

	/**
	 * The writer pairs. Each is one record written two ways: the REST request handler
	 * and the AI approval seam, with the side effects that act is supposed to have and
	 * the file those effects have to be real calls in.
	 *
	 * Page move is the second pair, and the one this map should always have had: it is
	 * the same act producing two different outcomes depending on who did it, which is
	 * the thing this guard exists to prevent.
	 *
	 * @return array<string,array{rest:array{0:string,1:string},ai:array{0:string,1:string},
	 *                            effects:array<string,string>,source:string}>
	 */
	function ai_write_side_effect_pairs(): array {
		$entry_effects = ai_write_side_effects();
		$module_source = "core/inc/bigtree/services/AutoModuleService.php";

		return [
			"module entry create" => [
				"rest" => [AutoModuleService::class, "create"],
				"ai" => [AutoModuleService::class, "aiCreateEntry"],
				"effects" => $entry_effects,
				"source" => $module_source,
			],
			"module entry update" => [
				"rest" => [AutoModuleService::class, "update"],
				"ai" => [AutoModuleService::class, "aiUpdateEntry"],
				"effects" => $entry_effects,
				"source" => $module_source,
			],
			"page move" => [
				"rest" => [PageService::class, "move"],
				"ai" => [PageService::class, "aiMovePage"],
				"effects" => ai_page_move_side_effects(),
				"source" => "core/inc/bigtree/services/PageService.php",
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
	 * the A1 defect, which every existing guard was structurally unable to see. Fails
	 * again on `recordRouteHistory` and `reindexPage` as it stood at 28f71d9c0, once
	 * page move joined the pair map (audit #25 B1/E5).
	 */
	function test_both_writers_of_a_record_run_the_same_side_effects() {
		$exemptions = ai_write_side_effect_exemptions();
		$diverged = [];

		foreach (ai_write_side_effect_pairs() as $pair => $seams) {
			foreach (array_keys($seams["effects"]) as $token) {
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
			"every declared side effect runs on both the REST and the AI write path"
		);
	}

	/**
	 * The declared side-effect list is real: a token nothing in the service calls is
	 * a guard checking for something that no longer exists, which passes forever.
	 */
	function test_the_declared_side_effects_all_exist() {
		$missing = [];

		foreach (ai_write_side_effect_pairs() as $pair => $seams) {
			$source = (string)file_get_contents(SERVER_ROOT . $seams["source"]);

			foreach ($seams["effects"] as $token => $reason) {
				if (strpos($source, $token) === false) {
					$missing[] = "{$pair}: {$token}";
				}

				T::ok($reason !== "", "the {$pair} \"{$token}\" side effect says what it does");
			}
		}

		T::equals(implode(", ", $missing), "", "every declared side effect is a call the service actually makes");
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

	// — E1 (audit #26): every writer of a pending change, discovered rather than paired —

	/**
	 * The two legacy primitives that write a `bigtree_pending_changes` row, and the
	 * zero-based position their publish-hook argument sits in.
	 *
	 * Both replace `publish_hook` wholesale — `"publish_hook" => $publish_hook ?: null`
	 * on every branch of both — so passing null is not "leave it alone", it is "erase
	 * whatever is there". The stored string is the only copy anything has: the SPA,
	 * REST approve and publish_pending_change all read it back off the row, and
	 * nothing can recover it once the proposal's 24h life is over.
	 *
	 * @return array<string,int> call => argument index
	 */
	function ai_pending_write_primitives(): array {

		return [
			// (module, table, id, data, mtm, tags, publish_hook, open_graph)
			"BigTreeAutoModule::submitChange" => 6,
			// (module, table, data, mtm, tags, publish_hook, embedded_form, open_graph)
			"BigTreeAutoModule::createPendingItem" => 5,
		];
	}

	/**
	 * The arguments of the call that starts at $offset in $source, as written.
	 *
	 * Parsed from the call rather than grepped for out of the method body, because
	 * "the body mentions formPublishHook somewhere" is exactly the check that cannot
	 * see a *second* write in the same method passing null — and a method with two
	 * queueing branches is the normal shape here (aiUpdateEntry has three).
	 *
	 * @return list<string>
	 */
	function ai_call_arguments(string $source, int $offset): array {
		$open = strpos($source, "(", $offset);

		if ($open === false) {

			return [];
		}

		$depth = 0;
		$argument = "";
		$arguments = [];
		$length = strlen($source);

		for ($i = $open; $i < $length; $i++) {
			$char = $source[$i];

			if ($char === '"' || $char === "'") {
				$quote = $char;
				$argument .= $char;

				while (++$i < $length) {
					$argument .= $source[$i];

					if ($source[$i] === "\\") {
						$argument .= $source[++$i] ?? "";

						continue;
					}

					if ($source[$i] === $quote) {

						break;
					}
				}

				continue;
			}

			if ($char === "(" || $char === "[") {
				$depth++;

				if ($depth === 1) {

					continue;
				}
			} elseif ($char === ")" || $char === "]") {
				$depth--;

				if ($depth === 0) {
					$arguments[] = trim($argument);

					return $arguments;
				}
			} elseif ($char === "," && $depth === 1) {
				$arguments[] = trim($argument);
				$argument = "";

				continue;
			}

			$argument .= $char;
		}

		return $arguments;
	}

	/** The name of the method $offset falls inside, or "" outside any. */
	function ai_enclosing_method(string $source, int $offset): string {
		$head = substr($source, 0, $offset);

		if (!preg_match_all('/function\s+([A-Za-z0-9_]+)\s*\(/', $head, $matches, PREG_OFFSET_CAPTURE)) {

			return "";
		}

		$last = end($matches[1]);

		return (string)$last[0];
	}

	/**
	 * Every pending-change write in AutoModuleService, discovered by scanning for the
	 * primitives above.
	 *
	 * @return list<array{call:string,method:string,hook:string,line:int}>
	 */
	function ai_pending_write_sites(): array {
		$path = SERVER_ROOT . "core/inc/bigtree/services/AutoModuleService.php";
		$source = (string)file_get_contents($path);
		$sites = [];

		foreach (ai_pending_write_primitives() as $call => $position) {
			$offset = 0;

			while (($found = strpos($source, $call . "(", $offset)) !== false) {
				$offset = $found + 1;
				$arguments = ai_call_arguments($source, $found);

				$sites[] = [
					"call" => $call,
					"method" => ai_enclosing_method($source, $found),
					"hook" => $arguments[$position] ?? "",
					"line" => substr_count(substr($source, 0, $found), "\n") + 1,
				];
			}
		}

		return $sites;
	}

	/**
	 * Pending-change writers that legitimately owe neither a publish hook nor the
	 * form hooks, with the reason.
	 *
	 * Empty today, and that is the point: an entry here is a claim that the omission is
	 * correct, never that the site was overlooked. A stale entry — one naming a site
	 * that no longer exists, or that now does the thing it was excused from — fails
	 * too.
	 *
	 * @return array<string,string> method => reason
	 */
	function ai_pending_write_exemptions(): array {

		return [];
	}

	/**
	 * E1: every writer of a pending change stores the form's publish hook and fires the
	 * form's hooks — discovered, not paired.
	 *
	 * The pair map above relates an AI seam to a REST twin, so a writer with no REST
	 * twin is not in it and no leg of this suite reads it. `aiQueueEntryTagChange` is
	 * exactly that: an admin tags an entry as part of a whole-form save, so the tag
	 * queue has no REST route of its own — and it passed a literal `null` for the
	 * publish hook, erasing the hook stored on whatever draft somebody else already had
	 * queued on the same entry. The effects map is also matched as *tokens in a method
	 * body*, which a second call in the same method can slip past.
	 *
	 * Fails on aiQueueEntryTagChange as the tree stood at 5de00e725 (audit #26 A1).
	 */
	function test_every_pending_change_writer_stores_the_forms_publish_hook() {
		$sites = ai_pending_write_sites();
		T::ok(count($sites) >= 5, "the sweep found the pending-change write sites (" . count($sites) . ")");

		$exemptions = ai_pending_write_exemptions();
		$null_hook = [];
		$no_form_hooks = [];
		$seen = [];

		foreach ($sites as $site) {
			$where = $site["method"] . "() line " . $site["line"];
			$seen[$site["method"]] = true;

			if (isset($exemptions[$site["method"]])) {

				continue;
			}

			// "null", "", or anything that isn't a resolution of the form's own hook.
			if (strpos($site["hook"], "formPublishHook(") === false) {
				$null_hook[] = $where . ' passes "' . $site["hook"] . '"';
			}

			$body = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, $site["method"]);

			if (strpos($body, "fireFormHooks") === false) {
				$no_form_hooks[] = $where;
			}
		}

		T::equals(
			implode(", ", $null_hook),
			"",
			"every pending-change write passes the form's publish hook, so approval can still fire it"
		);
		T::equals(
			implode(", ", $no_form_hooks),
			"",
			"…and sits in a writer that fires the module form's own hooks"
		);

		$stale = [];

		foreach ($exemptions as $method => $reason) {
			T::ok($reason !== "", "the {$method} exemption states why it owes neither");

			if (!isset($seen[$method])) {
				$stale[] = $method;
			}
		}

		T::equals(implode(", ", $stale), "", "no exemption names a writer that no longer queues a change");
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
		// Matched as a pattern rather than a literal because the same read now carries
		// the page's `parent` for the top-level nav clamp (audit #24 A2) — what this
		// leg is asserting is that the template is resolved from the page being
		// edited, not the exact column list it arrives in.
		$caller = ai_surface_method_body($service, "writePendingPageChange");
		T::ok(
			preg_match('/SELECT [^"]*\btemplate\b[^"]*FROM bigtree_pages WHERE id = \?/', $caller) === 1,
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

	/**
	 * E1b, the behavioural half: a tag change over an entry somebody already has a
	 * draft queued on leaves that draft's publish hook alone.
	 *
	 * This is the only defect in audit #26 that writes something the 24-hour proposal
	 * TTL doesn't clean up. `submitChange` replaces `publish_hook` wholesale on both
	 * its branches, so the literal `null` `aiQueueEntryTagChange` used to pass wrote a
	 * NULL over a hook name a *different* writer had stored — and the tagger need not
	 * have known a draft was there. Whoever approved the change later read the row,
	 * found nothing, and the module form's publish hook never ran for that record.
	 *
	 * Reproduced here in the order it happens: a queued edit, then a tag change over
	 * the same entry, then the approval that has to fire the hook.
	 */
	function test_a_tag_change_leaves_a_queued_drafts_publish_hook_alone() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_side_effect_enable_form_hooks();
		$svc = new AutoModuleService();
		$tags = new \BigTree\Services\TagService();
		$module_id = parity_news_module_id();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)[
			"id" => $editor_id,
			"level" => 0,
			"permissions" => ["module" => [$module_id => "e"]],
		];
		$entry_id = 0;
		$change_id = 0;
		$tag_id = 0;

		try {
			// A live entry, written by somebody who can publish.
			$created = ai_side_effect_create($svc, $dev, "zz AI Tag Hook " . bin2hex(random_bytes(3)));
			$entry_id = (int)($created["entry_id"] ?? 0);
			T::ok($entry_id > 0, "the fixture entry is live");

			// …and an editor's edit queued against it, which is where the hook is stored.
			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz AI Tag Hook edited"],
			], $editor);
			T::ok(!empty($validated["ok"]), "an editor's edit stages (" . (string)($validated["error"] ?? "") . ")");

			$queued = $svc->aiUpdateEntry($validated["payload"], $editor);
			T::equals((string)($queued["mode"] ?? ""), "pending", "and queues rather than publishing");

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'timber_news' AND item_id = ?",
				(string)$entry_id
			);
			T::ok($change_id > 0, "the queued change exists");
			T::equals(
				(string)SQL::fetchSingle("SELECT publish_hook FROM bigtree_pending_changes WHERE id = ?", $change_id),
				"ai_side_effect_publish_hook",
				"the draft carries the form's publish hook"
			);

			// Now the tag change, over the same entry, by the same editor. Coining a tag
			// stays administrator-only, so the tag exists already.
			$tag_name = "zz audit twentysix";
			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag_name,
				"route" => "zz-audit-twentysix-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			ai_side_effect_hook_log("reset");
			$staged = $tags->aiValidateAddTags([
				"module_id" => $module_id,
				"entry_id" => $entry_id,
				"tags" => [$tag_name],
			], $editor);
			T::ok(!empty($staged["ok"]), "the tag change stages (" . (string)($staged["error"] ?? "") . ")");

			$tagged = $tags->aiAddTags($staged["payload"], $editor);
			T::equals((string)($tagged["mode"] ?? ""), "pending", "an editor's tag change queues");
			T::equals(
				(int)($tagged["pending_change_id"] ?? 0),
				$change_id,
				"and folds into the change already queued rather than opening a second one"
			);

			// The defect, at the row: the content edit survived and the hook did not.
			T::equals(
				(string)SQL::fetchSingle("SELECT publish_hook FROM bigtree_pending_changes WHERE id = ?", $change_id),
				"ai_side_effect_publish_hook",
				"the queued draft's publish hook is still there after the tag change"
			);

			$changes = json_decode(
				(string)SQL::fetchSingle("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id),
				true
			) ?: [];
			T::equals((string)($changes["title"] ?? ""), "zz AI Tag Hook edited", "…and so is the queued edit");

			// The other half of the same omission: the form's post hook.
			T::ok(
				in_array("post:pending", ai_side_effect_hook_log(), true),
				"the tag change fires the form's post hook, told the row is not live"
			);

			// And the hook the row was carrying still fires when a publisher approves.
			ai_side_effect_hook_log("reset");
			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change_id);
			$apply = new ReflectionMethod(PendingChangeService::class, "applyPendingChange");
			$apply->setAccessible(true);
			$apply->invoke(new PendingChangeService(), $row, $dev);
			$change_id = 0;

			T::ok(
				in_array("publish:timber_news:{$entry_id}", ai_side_effect_hook_log(), true),
				"approving the change fires the publish hook the tag change did not erase"
			);
		} finally {
			if ($change_id) {
				parity_delete_pending($change_id);
			}

			if ($entry_id) {
				parity_delete_news_entries($entry_id);
			}

			parity_delete_tags($tag_id);
			parity_delete_users($dev_id, $editor_id);
			$restore();
			ai_side_effect_hook_log("reset");
		}
	}
