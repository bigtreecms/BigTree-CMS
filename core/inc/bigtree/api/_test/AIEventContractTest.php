<?php
	/**
	 * The `api.*` event contract — who fires, with what, and whether the
	 * approval that finishes a queued change fires anything at all.
	 *
	 * The module form's hooks are already checked for running the same
	 * extension-facing side effects on the AI write path as on the human one. This
	 * is the next question down, and three of its findings are invisible to
	 * AIWriteSideEffectParityTest for one structural reason: that guard's map pairs an
	 * AI seam with a REST twin and matches effects as *tokens in a body*, so
	 * `Hooks::fire` counts as satisfied no matter what is inside the parentheses.
	 *
	 *   E2  the payload contract — `Hooks::fire($event, $data)` makes `$data` the
	 *       extension's whole contract, and two events carried a key on the REST path
	 *       that the AI path dropped: `item` on module_entry.created/updated and
	 *       `path` on page.moved. A hook file written against the documented REST
	 *       shape silently no-opped on every assistant-driven write (A3). Also the
	 *       leg that fails when an event fires from only one of two surfaces (A4).
	 *   E3  the approval fires the record's event — approving a queued module-entry
	 *       change called updateItem / publishPendingItem and fired nothing at all, so
	 *       a subscriber saw publisher writes and missed every editor-authored change
	 *       once it was approved (A2). The page half of the same method is the
	 *       positive control: it replays through performCreate/performUpdate, which
	 *       have always fired.
	 *
	 * E2 is a source sweep and needs no database; E3 drives real records through the
	 * real approval path with a fixture hook file registered in the live registry.
	 * (ai_call_arguments() / ai_enclosing_method() come from
	 * AIWriteSideEffectParityTest; all *Test.php load before any test_* runs.)
	 */

	use BigTree\Api\Hooks;
	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\PendingChangeService;

	// — E2: the payload contract —

	/**
	 * Every `api.*` event, and the payload keys it is contracted to carry.
	 *
	 * Three shapes:
	 *
	 *   - `required` — keys every fire of this event must pass. A subscriber may rely
	 *     on them being there.
	 *   - `optional` — keys some fire sites carry and others don't, because the act
	 *     genuinely differs (a queued page draft has an `id` when it edits a page and
	 *     a `parent` when it creates one). Declared so that a *new* key is still a
	 *     deliberate act rather than a silent divergence.
	 *   - `row` — the payload is a whole database row rather than a key set, passed
	 *     as a variable. The value is the reason it is one.
	 *
	 * `via` is additive and allowed everywhere: it says an assistant did this, and a
	 * subscriber that doesn't care never looks at it.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ai_event_payload_contract(): array {

		return [
			"page.created" => ["row" => "the whole bigtree_pages row performCreate just wrote"],
			"page.updated" => ["row" => "the whole bigtree_pages row, re-read after the write"],
			"page.deleted" => ["row" => "the whole bigtree_pages row as it stood before the delete"],
			"page.archived" => ["required" => ["id"]],
			"page.unarchived" => ["required" => ["id"]],
			// `path` is the new URL, and the one fact a mover's subscriber actually
			// wants. REST carried it; the AI seam didn't.
			"page.moved" => ["required" => ["id", "parent", "path"]],
			"page.pending_created" => [
				"required" => ["parent", "pending_change_id"],
				"optional" => ["duplicated_from"],
			],
			// A queued page change is an EDIT on a page (`id`) or a NEW under a parent
			// (`parent`); the change id is the only thing both always have.
			"page.pending_updated" => [
				"required" => ["pending_change_id"],
				"optional" => ["id", "parent"],
			],
			// `item` is the row the subscriber would otherwise have to re-query.
			"module_entry.created" => ["required" => ["module", "table", "id", "item"]],
			"module_entry.updated" => ["required" => ["module", "table", "id", "item"]],
			"module_entry.pending_created" => ["required" => ["module", "table", "pending_id"]],
			"module_entry.pending_updated" => ["required" => ["module", "table", "id"]],
			"module_entry.deleted" => ["required" => ["module", "table", "id"]],
			"module_entry.draft_discarded" => ["required" => ["module", "table", "id"]],
			// The featured/archived-style column flags, whose event name is the column.
			"module_entry.{flag}" => ["required" => ["module", "table", "id", "value"]],
			"pending_change.rejected" => ["required" => ["id", "table"]],
			"resource.uploaded" => ["row" => "the whole bigtree_resources row"],
			"resource.replaced" => ["row" => "the whole bigtree_resources row, after the replacement"],
			"resource.deleted" => ["row" => "the whole bigtree_resources row as it stood before the delete"],
		];
	}

	/** A file's PHP with its comments removed, so a sweep greps code and not prose. */
	function ai_file_without_comments(string $source): string {
		$code = "";

		foreach (token_get_all($source) as $token) {
			if (!is_array($token)) {
				$code .= $token;

				continue;
			}

			if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
				// Keep the newlines so a stripped comment doesn't join two statements.
				$code .= str_repeat("\n", substr_count($token[1], "\n"));

				continue;
			}

			$code .= $token[1];
		}

		return $code;
	}

	/** The balanced `[...]` that starts at $open, as written. */
	function ai_balanced_array(string $source, int $open): string {
		$depth = 0;
		$length = strlen($source);

		for ($i = $open; $i < $length; $i++) {
			$char = $source[$i];

			if ($char === '"' || $char === "'") {
				$quote = $char;

				while (++$i < $length) {
					if ($source[$i] === "\\") {
						$i++;

						continue;
					}

					if ($source[$i] === $quote) {

						break;
					}
				}

				continue;
			}

			if ($char === "[") {
				$depth++;
			} elseif ($char === "]") {
				$depth--;

				if ($depth === 0) {

					return substr($source, $open, $i - $open + 1);
				}
			}
		}

		return "";
	}

	/**
	 * The keys an array literal declares at its own top level.
	 *
	 * Depth-aware because a value can be an array of its own (`$fresh["item"]`), and a
	 * key harvested out of one would be a key the subscriber never sees.
	 *
	 * @return list<string>
	 */
	function ai_array_literal_keys(string $literal): array {
		$keys = [];
		$depth = 0;
		$pending = null;
		$length = strlen($literal);

		for ($i = 0; $i < $length; $i++) {
			$char = $literal[$i];

			if ($char === '"' || $char === "'") {
				$quote = $char;
				$string = "";

				while (++$i < $length && $literal[$i] !== $quote) {
					$string .= $literal[$i];
				}

				if ($depth === 1) {
					$pending = $string;
				}

				continue;
			}

			if ($char === "[" || $char === "(") {
				$depth++;

				continue;
			}

			if ($char === "]" || $char === ")") {
				$depth--;

				continue;
			}

			if ($depth !== 1) {

				continue;
			}

			if ($char === "=" && ($literal[$i + 1] ?? "") === ">") {
				if ($pending !== null) {
					$keys[] = $pending;
				}

				$pending = null;
				$i++;

				continue;
			}

			if ($char === ",") {
				$pending = null;
			}
		}

		return $keys;
	}

	/**
	 * Every `Hooks::fire` in the services, with the event it fires and the payload it
	 * passes.
	 *
	 * Discovered rather than listed, which is what makes this leg fail when a *new*
	 * event appears with no declaration — the other half of "a payload key set is a
	 * per-event constant".
	 *
	 * @return list<array{event:string,file:string,path:string,method:string,payload:string,literal:?string}>
	 */
	function ai_event_fire_sites(): array {
		$sites = [];

		foreach (glob(__DIR__ . "/../../services/*.php") ?: [] as $file) {
			$source = ai_file_without_comments((string)file_get_contents($file));
			$offset = 0;

			while (($found = strpos($source, "Hooks::fire(", $offset)) !== false) {
				$offset = $found + 1;
				$arguments = ai_call_arguments($source, $found + strlen("Hooks::fire"));
				$event = trim((string)($arguments[0] ?? ""), "\"'");
				// "module_entry.{$column}" and "module_entry.{$flag}" are one contract:
				// the event name is the column, and the payload is the same either way.
				$event = (string)preg_replace('/\{\$[A-Za-z0-9_]+\}/', "{flag}", $event);
				$payload = trim((string)($arguments[1] ?? ""));
				$literal = null;

				if (strncmp($payload, "[", 1) === 0) {
					$literal = $payload;
				} elseif (preg_match('/^\$[A-Za-z0-9_]+$/', $payload)) {
					// A payload built a few lines above the fire, which is how the two
					// shared helpers (fireEntryDeleted, fireChangeRejected) pass one.
					$method_start = 0;

					if (preg_match_all(
						'/function\s+[A-Za-z0-9_]+\s*\(/',
						substr($source, 0, $found),
						$functions,
						PREG_OFFSET_CAPTURE
					)) {
						$last = end($functions[0]);
						$method_start = (int)$last[1];
					}

					$slice = substr($source, $method_start, $found - $method_start);
					$assignment = preg_quote($payload, "/") . '\s*=\s*\[';

					if (preg_match_all('/' . $assignment . '/', $slice, $found_at, PREG_OFFSET_CAPTURE)) {
						$last = end($found_at[0]);
						$open = $method_start + (int)$last[1] + strlen((string)$last[0]) - 1;
						$literal = ai_balanced_array($source, $open);
					}
				}

				$sites[] = [
					"event" => $event,
					"file" => basename($file),
					"path" => $file,
					"method" => ai_enclosing_method($source, $found),
					"payload" => $payload,
					"literal" => $literal,
				];
			}
		}

		return $sites;
	}

	/**
	 * E2: every `api.*` fire matches its event's declared payload contract.
	 *
	 * Fails on `module_entry.created`, `module_entry.updated` and `page.moved` as the
	 * tree stood at 5de00e725 — the AI seams dropped `item` and `path`.
	 */
	function test_every_event_fires_with_its_declared_payload() {
		$contract = ai_event_payload_contract();
		$sites = ai_event_fire_sites();
		T::ok(count($sites) >= 30, "the sweep found the api.* fire sites (" . count($sites) . ")");

		$undeclared = [];
		$mismatched = [];
		$fired = [];

		foreach ($sites as $site) {
			$where = $site["file"] . "::" . $site["method"] . "() \"" . $site["event"] . "\"";

			if (!isset($contract[$site["event"]])) {
				$undeclared[] = $where;

				continue;
			}

			$fired[$site["event"]] = true;
			$declared = $contract[$site["event"]];

			if (isset($declared["row"])) {
				if ($site["literal"] !== null) {
					$mismatched[] = $where . " passes a key set where the whole row is contracted";
				}

				continue;
			}

			if ($site["literal"] === null) {
				$mismatched[] = $where . " passes \"" . $site["payload"] . "\", which no key set could be read from";

				continue;
			}

			$keys = ai_array_literal_keys($site["literal"]);
			$required = (array)($declared["required"] ?? []);
			$allowed = array_merge($required, (array)($declared["optional"] ?? []), ["via"]);

			foreach (array_diff($required, $keys) as $missing) {
				$mismatched[] = $where . " is missing \"" . $missing . "\"";
			}

			foreach (array_diff($keys, $allowed) as $extra) {
				$mismatched[] = $where . " carries undeclared \"" . $extra . "\"";
			}
		}

		T::equals(
			implode(", ", $undeclared),
			"",
			"every api.* event a service fires has a declared payload contract"
		);
		T::equals(
			implode(", ", $mismatched),
			"",
			"every fire of an event carries exactly the keys that event is contracted to carry"
		);

		// …and nothing lingers in the contract that nothing fires any more: a stale
		// declaration is a contract nobody can break, which passes forever.
		$stale = [];

		foreach ($contract as $event => $declared) {
			if (isset($declared["row"])) {
				T::ok($declared["row"] !== "", "the {$event} contract says why its payload is a whole row");
			}

			if (!isset($fired[$event])) {
				$stale[] = $event;
			}
		}

		T::equals(implode(", ", $stale), "", "no declared event outlives the code that fires it");
	}

	/**
	 * A service file's comment-stripped source, and where each of its methods begins
	 * and ends. Read once per file.
	 *
	 * @return array{0:string,1:array<string,array{0:int,1:int}>}
	 */
	function ai_service_source(string $path): array {
		static $cache = [];

		if (!isset($cache[$path])) {
			$source = ai_file_without_comments((string)file_get_contents($path));
			$ranges = [];

			if (preg_match_all('/function\s+([A-Za-z0-9_]+)\s*\(/', $source, $found, PREG_OFFSET_CAPTURE)) {
				$count = count($found[1]);

				for ($i = 0; $i < $count; $i++) {
					$ranges[(string)$found[1][$i][0]] = [
						(int)$found[0][$i][1],
						$i + 1 < $count ? (int)$found[0][$i + 1][1] : strlen($source),
					];
				}
			}

			$cache[$path] = [$source, $ranges];
		}

		return $cache[$path];
	}

	/**
	 * The surfaces that can reach a fire in $method: the `ai*` seams and the REST
	 * request handlers, with private helpers resolved through their callers.
	 *
	 * Needed because the fix for an inversion is usually to route both surfaces through
	 * one helper — which is the right shape, and which also makes "who fires this?"
	 * unanswerable from the fire site alone. `fireChangeRejected` is exactly that: it
	 * is neither surface, and both call it.
	 *
	 * @param array<string,array{0:int,1:int}> $ranges
	 * @param array<string,bool> $seen
	 * @return list<string>
	 */
	function ai_event_surfaces(string $source, array $ranges, string $method, array $seen = []): array {
		if ($method === "" || isset($seen[$method])) {

			return [];
		}

		$seen[$method] = true;

		// An ai* seam is a surface in its own right; anything else is only a surface if
		// nothing else calls it.
		if (strncmp($method, "ai", 2) === 0) {

			return [$method];
		}

		$callers = [];

		foreach ($ranges as $name => [$start, $end]) {
			if ($name === $method) {

				continue;
			}

			if (strpos(substr($source, $start, $end - $start), '$this->' . $method . "(") !== false) {
				$callers[] = $name;
			}
		}

		if (!$callers) {

			return [$method];
		}

		$out = [];

		foreach ($callers as $caller) {
			$out = array_merge($out, ai_event_surfaces($source, $ranges, $caller, $seen));
		}

		return $out ?: [$method];
	}

	/**
	 * E2, the inversion leg: an event only the assistant can make happen.
	 *
	 * This is the shape page.archived / unarchived / moved once had — "these three
	 * hooks used to fire on the AI path only", so an extension listening for an archive
	 * saw assistant-driven ones and missed every one made in the admin — and the same
	 * shape one service over, where `pending_change.rejected` fired from
	 * `aiRejectChange` and REST's `reject()` deleted the row in silence.
	 *
	 * The assistant is the rarer writer of every record type here, so an event it alone
	 * produces is always backwards.
	 *
	 * Fails on `pending_change.rejected` as the tree stood at 5de00e725.
	 */
	function test_no_event_fires_from_the_assistant_alone() {
		$surfaces = [];

		foreach (ai_event_fire_sites() as $site) {
			[$source, $ranges] = ai_service_source($site["path"]);

			foreach (ai_event_surfaces($source, $ranges, $site["method"]) as $surface) {
				$surfaces[$site["event"]][$surface] = true;
			}
		}

		T::ok(count($surfaces) >= 15, "the sweep resolved a surface for every event (" . count($surfaces) . ")");

		$assistant_only = [];

		foreach ($surfaces as $event => $methods) {
			$human = false;

			foreach (array_keys($methods) as $method) {
				if (strncmp($method, "ai", 2) !== 0) {
					$human = true;
				}
			}

			if (!$human) {
				$assistant_only[] = $event . " (" . implode(", ", array_keys($methods)) . ")";
			}
		}

		T::equals(
			implode(", ", $assistant_only),
			"",
			"no api.* event is something only the assistant can make happen"
		);
	}

	// — E3: the approval fires the record's own event —

	/**
	 * Event log for the fixture hook files in fixtures/hook_event_*.php. `$op` is
	 * "add", "reset" or "read".
	 *
	 * @return list<array{0:string,1:mixed}>
	 */
	function ai_event_log(string $op = "read", string $event = "", $data = null): array {
		static $log = [];

		if ($op === "reset") {
			$log = [];
		} elseif ($op === "add") {
			$log[] = [$event, $data];
		}

		return $log;
	}

	/** The recorder the fixture hook files call. */
	function ai_event_log_record(string $event, $data): void {
		ai_event_log("add", $event, $data);
	}

	/** Every payload logged for $event this run. */
	function ai_event_payloads(string $event): array {
		$out = [];

		foreach (ai_event_log() as [$logged, $data]) {
			if ($logged === $event) {
				$out[] = $data;
			}
		}

		return $out;
	}

	/**
	 * Point the live hook registry at the fixture files for $events, and hand back the
	 * restore closure. Merges into the on-disk registry and restores the exact original
	 * bytes, the way HooksTest does, so the real callout registry is untouched.
	 *
	 * @param list<string> $events
	 */
	function ai_event_capture(array $events): callable {
		$cache_path = SERVER_ROOT . Hooks::CACHE_FILE;
		$original = file_exists($cache_path) ? file_get_contents($cache_path) : null;
		$registry = $original ? (json_decode($original, true) ?: []) : [];

		foreach ($events as $event) {
			$registry["api." . $event] = [
				"core/inc/bigtree/api/_test/fixtures/hook_event_" . str_replace(".", "_", $event) . ".php",
			];
		}

		file_put_contents($cache_path, json_encode($registry));
		Hooks::clearCache();
		ai_event_log("reset");

		return function () use ($cache_path, $original): void {
			if ($original !== null) {
				file_put_contents($cache_path, $original);
			} else {
				@unlink($cache_path);
			}

			Hooks::clearCache();
			ai_event_log("reset");
		};
	}

	/** Approve a queued change the way REST approve() and publish_pending_change both do. */
	function ai_event_approve(int $change_id, $user): array {
		$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change_id);
		$apply = new ReflectionMethod(PendingChangeService::class, "applyPendingChange");
		$apply->setAccessible(true);

		return (array)$apply->invoke(new PendingChangeService(), $row, $user);
	}

	/**
	 * E3: approving a queued module-entry change fires the record's own event.
	 *
	 * Both branches, because they are different acts through different primitives: a
	 * NEW draft is *promoted* by publishPendingItem and an EDIT is applied in place by
	 * updateItem. Neither fires anything of its own — grep `Hooks::fire` in
	 * auto-modules.php and there are no hits — so without these an extension
	 * mirroring rows into a search service saw the publisher's write and missed the
	 * editor's entirely, which is the assistant's default path.
	 */
	function test_approving_a_queued_entry_change_fires_the_entry_event() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_event_capture(["module_entry.created", "module_entry.updated"]);
		$svc = new AutoModuleService();
		$module_id = parity_news_module_id();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)[
			"id" => $editor_id,
			"level" => 0,
			"permissions" => ["module" => [$module_id => "e"]],
		];
		$new_entry_id = 0;
		$edited_entry_id = 0;
		$change_id = 0;

		try {
			// — a queued NEW, approved —
			$queued = ai_side_effect_create($svc, $editor, "zz AI Event New " . bin2hex(random_bytes(3)));
			T::equals((string)($queued["mode"] ?? ""), "pending", "an editor's create queues");

			$change_id = (int)($queued["pending_id"] ?? 0);
			$applied = ai_event_approve($change_id, $dev);
			$new_entry_id = (int)($applied["item_id"] ?? 0);
			$change_id = 0;
			T::ok($new_entry_id > 0, "approving it promotes the draft to a live row");

			$created = ai_event_payloads("module_entry.created");
			T::equals(count($created), 1, "approving a queued NEW fires module_entry.created once");
			T::equals(count(ai_event_payloads("module_entry.updated")), 0, "…and not the update event");

			$payload = $created[0] ?? [];
			T::equals((int)($payload["id"] ?? 0), $new_entry_id, "the payload names the row that went live");
			T::equals((string)($payload["table"] ?? ""), "timber_news", "…its table");
			T::equals((string)($payload["module"] ?? ""), $module_id, "…and its module");
			T::ok(
				is_array($payload["item"] ?? null) && (int)($payload["item"]["id"] ?? 0) === $new_entry_id,
				"…and carries the row itself, so a subscriber needn't re-query it"
			);

			// — a queued EDIT on a live row, approved —
			$live = ai_side_effect_create($svc, $dev, "zz AI Event Edit " . bin2hex(random_bytes(3)));
			$edited_entry_id = (int)($live["entry_id"] ?? 0);
			T::ok($edited_entry_id > 0, "a live entry to edit");

			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => (string)$edited_entry_id,
				"data" => ["title" => "zz AI Event Edited"],
			], $editor);
			T::ok(!empty($validated["ok"]), "an editor's edit stages (" . (string)($validated["error"] ?? "") . ")");

			$svc->aiUpdateEntry($validated["payload"], $editor);
			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'timber_news' AND item_id = ?",
				(string)$edited_entry_id
			);
			T::ok($change_id > 0, "and queues a change");

			// From here on the log holds only what the approval itself fired: the live
			// create above is a module_entry.created of its own.
			ai_event_log("reset");
			ai_event_approve($change_id, $dev);
			$change_id = 0;

			$updated = ai_event_payloads("module_entry.updated");
			T::equals(count($updated), 1, "approving a queued EDIT fires module_entry.updated once");
			T::equals(count(ai_event_payloads("module_entry.created")), 0, "…and not the create event");
			T::equals(
				(string)(($updated[0]["item"]["title"] ?? "")),
				"zz AI Event Edited",
				"and the row it carries is the row as the approval left it"
			);
		} finally {
			if ($change_id) {
				parity_delete_pending($change_id);
			}

			parity_delete_news_entries($new_entry_id, $edited_entry_id);
			parity_delete_users($dev_id, $editor_id);
			$restore();
		}
	}

	/**
	 * The positive control, one service over and in the same method: approving a queued
	 * *page* change has always fired page.updated, because publishPendingChange replays
	 * the change through performCreate / performUpdate rather than through a legacy
	 * primitive that fires nothing. Same act, same file, and it is the answer the entry
	 * half should always have given.
	 */
	function test_approving_a_queued_page_change_fires_the_page_event() {
		if (!parity_db_available()) {

			return;
		}

		$restore = ai_event_capture(["page.updated"]);
		$pages = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;
		$editor_id = 0;
		$change_id = 0;

		try {
			$page_id = parity_a5_page($pages, $dev);
			T::ok($page_id > 0, "fixture page created");

			[$editor_id, $editor] = parity_a5_page_editor($page_id);

			$validated = $pages->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"nav_title" => "AI Audit26 Page Edited",
			], $editor);
			T::ok(!empty($validated["ok"]), "an editor's page edit stages (" . (string)($validated["error"] ?? "") . ")");

			$queued = $pages->aiUpdatePage($validated["payload"], $editor);
			T::equals((string)($queued["mode"] ?? ""), "pending", "and queues");

			$change_id = (int)($queued["pending_change_id"] ?? 0);
			T::ok($change_id > 0, "the queued page change exists");

			ai_event_log("reset");
			ai_event_approve($change_id, $dev);
			$change_id = 0;

			$updated = ai_event_payloads("page.updated");
			T::ok(count($updated) >= 1, "approving a queued page change fires page.updated");
			T::equals(
				(int)(($updated[0]["id"] ?? 0)),
				$page_id,
				"and the whole row it carries is the page that went live"
			);
		} finally {
			if ($change_id) {
				parity_delete_pending($change_id);
			}

			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
			$restore();
		}
	}

	/**
	 * A4's second inversion: a publisher's tag write lands live and now says so.
	 *
	 * The queued half always fired `module_entry.pending_updated`, while the live
	 * half wrote `bigtree_tags_rel` rows directly and fired nothing — so the
	 * *more* consequential of the two writes was the one no subscriber could see.
	 * Same event name the entry write path uses, through one helper,
	 * so there is a single token to match and a single place for the two record kinds
	 * to agree.
	 */
	function test_a_live_tag_write_fires_the_records_own_event() {
		if (!parity_ai_processors_ready()) {

			return;
		}

		$restore = ai_event_capture(["module_entry.updated"]);
		// The News form ships with tagging off, and add_tags refuses that form — so a
		// fixture that tags an entry turns it on first, exactly as the entry-write
		// fixtures do.
		$tagging = parity_enable_news_relations();
		$svc = new AutoModuleService();
		$tags = new \BigTree\Services\TagService();
		$module_id = parity_news_module_id();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;
		$tag_id = 0;

		try {
			$created = ai_side_effect_create($svc, $dev, "zz AI Tag Event " . bin2hex(random_bytes(3)));
			$entry_id = (int)($created["entry_id"] ?? 0);
			T::ok($entry_id > 0, "a live entry to tag");

			$tag_name = "zz audit twentysix live";
			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag_name,
				"route" => "zz-audit-twentysix-live-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			$staged = $tags->aiValidateAddTags([
				"module_id" => $module_id,
				"entry_id" => $entry_id,
				"tags" => [$tag_name],
			], $dev);
			T::ok(!empty($staged["ok"]), "the tag change stages (" . (string)($staged["error"] ?? "") . ")");

			ai_event_log("reset");
			$tagged = $tags->aiAddTags($staged["payload"], $dev);
			T::equals((string)($tagged["mode"] ?? ""), "tagged", "a publisher's tag write lands live");

			$fired = ai_event_payloads("module_entry.updated");
			T::equals(count($fired), 1, "and fires the entry's own updated event");
			T::equals((int)($fired[0]["id"] ?? 0), $entry_id, "naming the row that changed");

			// The inverse write, for the same reason.
			ai_event_log("reset");
			$staged = $tags->aiValidateRemoveTags([
				"module_id" => $module_id,
				"entry_id" => $entry_id,
				"tags" => [$tag_name],
			], $dev);
			T::ok(!empty($staged["ok"]), "the untag stages (" . (string)($staged["error"] ?? "") . ")");

			$tags->aiRemoveTags($staged["payload"], $dev);
			T::equals(count(ai_event_payloads("module_entry.updated")), 1, "removing a tag fires it too");

			// …and a call that changes nothing is not an event: the tag is already gone.
			ai_event_log("reset");
			$tags->aiRemoveTags($staged["payload"], $dev);
			T::equals(count(ai_event_payloads("module_entry.updated")), 0, "a no-op tag write fires nothing");
		} finally {
			parity_delete_tags($tag_id);
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
			$tagging();
			$restore();
		}
	}
