<?php
	/**
	 * The per-form and per-view feature switches.
	 *
	 * Other guards ask whether a *field's* configuration is complete enough for a
	 * future write. This asks the level up. A module form and a module
	 * view each carry switches that decide whether a column or a relation is
	 * reachable in the admin at all — the form's `tagging` / `open_graph` flags, and
	 * the view's `approve` / `feature` / `archive` actions. Every read seam discloses
	 * them; the entry write seams enforce the form's; and two writers that reach the
	 * same state without going through the entry form did not.
	 *
	 *  - **E1 (behavioural).** `add_tags` wrote `bigtree_tags_rel` rows against a form
	 *    with tagging off — rows no admin screen can show or remove, and which the
	 *    entry's next ordinary save silently deletes (auto-modules.php:456 clears the
	 *    record's relations on every write, and a tagging-off form re-submits none).
	 *    It refuses now. `remove_tags` deliberately still works there: it is the only
	 *    path that can reach rows already written, so it discloses instead.
	 *  - **E2 (structural).** The generalization that would have found it: enumerate
	 *    the per-form switches, *discover* every seam that writes the state each one
	 *    governs, and require each to resolve to the shared gate or say why not. The
	 *    per-form twin of AILinkColumnGuardTest's per-column sweep.
	 *  - **E3.** A status column with no matching module action can be flipped by this
	 *    tool and by nothing else in the admin — status columns are not form fields
	 *    either, so the entry editor doesn't render them. Disclosed, not refused,
	 *    because the REST toggle does it too.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\TagService;

	/**
	 * A throwaway module over a throwaway table, so a switch can be off (and then on)
	 * without touching the shared News fixture every other AI test shares.
	 *
	 * @param array<string,mixed> $options `tagging`, `columns`, `view_actions`.
	 * @return array{0:string,1:string,2:string} [module id, form id, table]
	 */
	function ai_feature_switch_module(string $suffix, array $options = []): array {
		$table = "zz_audit27_{$suffix}";
		$columns = (array)($options["columns"] ?? []);
		$extra = "";

		foreach ($columns as $column) {
			$extra .= ", `" . $column . "` CHAR(2) NOT NULL DEFAULT ''";
		}

		SQL::query("CREATE TABLE `{$table}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
			. "`title` VARCHAR(191) NOT NULL DEFAULT ''{$extra}, PRIMARY KEY (`id`)) ENGINE=InnoDB "
			. "DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$form = [
			"id" => "form-{$suffix}",
			"title" => "Entry",
			"table" => $table,
			"fields" => [
				["id" => "title", "title" => "Title", "type" => "text", "column" => "title", "settings" => []],
			],
		];

		// Absent rather than "off": the flag a module form has never been given is the
		// state every scaffolded module starts in, and the one the finding is about.
		if (!empty($options["tagging"])) {
			$form["tagging"] = "on";
		}

		$module_id = BigTreeJSONDB::insert("modules", [
			"name" => "ZZ Audit27 {$suffix}",
			"route" => "zz-audit27-{$suffix}",
			// The module class list is rebuilt from every module record whenever
			// modules.json changes and reads this key unguarded.
			"class" => "",
			// Legacy auto-modules reads both of these unguarded while recaching a view
			// (auto-modules.php:222, :269, :273, :662), and it walks *every* module's
			// views — so a fixture missing them warns from other tests' writes too.
			"gbp" => ["name" => "", "table" => "", "item_parser" => "", "other_table" => ""],
			"forms" => [$form],
			"views" => [[
				"id" => "view-{$suffix}",
				"title" => "Entries",
				"type" => "searchable",
				"table" => $table,
				"fields" => ["title" => ["width" => "", "title" => "Title", "parser" => "", "numeric" => false]],
				"settings" => ["filter" => "", "sort" => "`id` DESC", "per_page" => "15"],
				"actions" => (array)($options["view_actions"] ?? ["edit" => "on"]),
			]],
		]);

		return [(string)$module_id, "form-{$suffix}", $table];
	}

	/** Drop a fixture module and its table. Best-effort: a fixture must not fail a run. */
	function ai_feature_switch_teardown(string $suffix, string $table): void {
		// By route rather than by id: a store that refused the write still returns
		// false from insert, and the route is this test's own either way.
		BigTreeJSONDB::delete("modules", "zz-audit27-{$suffix}", "route");

		try {
			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = ?", $table);
			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", "view-{$suffix}");
			SQL::query("DROP TABLE IF EXISTS `{$table}`");
		} catch (Throwable $e) {
			// disposable
		}
	}

	/**
	 * E1: add_tags refuses a form whose tagging switch is off, and stages once it is
	 * on. Both legs against the same module, so the *only* thing that differs between
	 * the refusal and the staging is the switch.
	 */
	function test_add_tags_refuses_a_form_whose_tagging_switch_is_off() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$tag_id = 0;

		try {
			[$module_id, $form_id, $table] = ai_feature_switch_module($suffix);
			T::ok($module_id !== "", "the fixture module was written to the store");

			$entry_id = (int)SQL::insert($table, ["title" => "ZZ Audit27 Taggable"]);
			$tag_name = "zz audit twentyseven gate";
			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag_name,
				"route" => "zz-audit-twentyseven-gate-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			$tags = new TagService();
			$developer = (object)["id" => 0, "level" => 2, "permissions" => []];
			$args = ["module_id" => $module_id, "form" => $form_id, "entry_id" => $entry_id, "tags" => [$tag_name]];

			$refused = $tags->aiValidateAddTags($args, $developer);
			T::ok(empty($refused["ok"]), "add_tags does not stage against a form without tagging");
			T::ok(
				strpos((string)($refused["error"] ?? ""), "doesn't have tagging enabled") !== false,
				"and refuses with the sentence the entry write seams have always used"
			);
			T::ok(
				strpos((string)($refused["error"] ?? ""), "Developer → Modules") !== false,
				"…which names where to turn it on"
			);
			T::equals(
				(int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_tags_rel WHERE `table` = ? AND entry = ?",
					$table,
					(string)$entry_id
				),
				0,
				"nothing was written"
			);

			// The switch is the only thing that changes.
			$module = BigTreeJSONDB::get("modules", $module_id);
			$module["forms"][0]["tagging"] = "on";
			BigTreeJSONDB::update("modules", $module_id, $module);

			$staged = $tags->aiValidateAddTags($args, $developer);
			T::ok(!empty($staged["ok"]), "with tagging on it stages (" . (string)($staged["error"] ?? "") . ")");

			$written = $tags->aiAddTags($staged["payload"], $developer);
			T::equals((string)($written["mode"] ?? ""), "tagged", "and a publisher's approval lands it live");

			// The approval-time re-ask: the switch can be turned off inside the
			// proposal's 24h TTL, so the stored payload is not the authority.
			$module["forms"][0]["tagging"] = "";
			BigTreeJSONDB::update("modules", $module_id, $module);

			$stale = $tags->aiAddTags($staged["payload"], $developer);
			T::equals((string)($stale["mode"] ?? ""), "error", "a proposal approved after the switch goes off errors");
			T::ok(
				strpos((string)($stale["message"] ?? ""), "doesn't have tagging enabled") !== false,
				"…saying which switch stopped it"
			);
		} finally {
			ai_feature_switch_teardown($suffix, $table);

			if (function_exists("parity_delete_tags")) {
				parity_delete_tags($tag_id);
			}
		}
	}

	/**
	 * E1, second leg: remove_tags is deliberately still permitted on a tagging-off
	 * form, because it is the only surface that can reach rows written before the
	 * gate existed. Refusing there would strand them.
	 */
	function test_remove_tags_still_clears_tags_on_a_form_without_tagging() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$tag_id = 0;

		try {
			[$module_id, $form_id, $table] = ai_feature_switch_module($suffix);
			$entry_id = (int)SQL::insert($table, ["title" => "ZZ Audit27 Stranded"]);
			$tag_name = "zz audit twentyseven stranded";
			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag_name,
				"route" => "zz-audit-twentyseven-stranded-" . bin2hex(random_bytes(3)),
				"usage_count" => 1,
			]);

			// The state the bug left behind: a relation on a form that cannot show it.
			SQL::insert("bigtree_tags_rel", ["table" => $table, "entry" => (string)$entry_id, "tag" => $tag_id]);

			$tags = new TagService();
			$developer = (object)["id" => 0, "level" => 2, "permissions" => []];
			$staged = $tags->aiValidateRemoveTags([
				"module_id" => $module_id,
				"form" => $form_id,
				"entry_id" => $entry_id,
				"tags" => [$tag_name],
			], $developer);

			T::ok(!empty($staged["ok"]), "remove_tags stages anyway (" . (string)($staged["error"] ?? "") . ")");
			T::ok(
				strpos((string)$staged["summary"], "invisible in the admin") !== false,
				"and the card says why these tags cannot be seen"
			);
			T::ok(
				strpos((string)$staged["summary"], "only way to clear them") !== false,
				"…and that this is the only way to clear them"
			);

			$removed = $tags->aiRemoveTags($staged["payload"], $developer);
			T::equals((string)($removed["mode"] ?? ""), "untagged", "the removal lands");
			T::ok(
				strpos((string)($removed["note"] ?? ""), "invisible in the admin") !== false,
				"and the result carries the same clause the card did"
			);
			T::equals(
				(int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_tags_rel WHERE `table` = ? AND entry = ?",
					$table,
					(string)$entry_id
				),
				0,
				"the stranded relation is gone"
			);
		} finally {
			ai_feature_switch_teardown($suffix, $table);

			if (function_exists("parity_delete_tags")) {
				parity_delete_tags($tag_id);
			}
		}
	}

	/**
	 * The per-form feature switches, each with the shared gate a write to the state it
	 * governs must resolve, and the markers that *discover* such a write.
	 *
	 * The markers are deliberately about the stored state rather than about the tool:
	 * `add_tags` was invisible to every existing guard because it is an AI-only
	 * writer with no REST twin (AIWriteSideEffectParityTest's AI↔REST pair map cannot
	 * see one), and because nothing enumerated the *places* tag relations are written.
	 *
	 * @return array<string,array{gate:string,markers:list<string>}>
	 */
	function ai_form_feature_switches(): array {

		return [
			"tagging" => [
				"gate" => "aiFormTaggingError(",
				"markers" => [
					'/SQL::(insert|delete)\("bigtree_tags_rel"/',
					'/(INSERT INTO|DELETE\s+[\w.]*\s*FROM|UPDATE)\s+bigtree_tags_rel/i',
					'/aiQueueEntryTagChange\(/',
					'/syncTags\(/',
				],
			],
			"open_graph" => [
				"gate" => "aiFormRelationError(",
				"markers" => [
					'/SQL::(insert|delete)\("bigtree_open_graph"/',
					'/(INSERT INTO|DELETE\s+[\w.]*\s*FROM|UPDATE)\s+bigtree_open_graph/i',
					'/syncOpenGraph\(/',
				],
			],
		];
	}

	/**
	 * Writers of a switched relation's state that legitimately do not resolve the
	 * gate, with the reason. Keyed "switch Class::method".
	 *
	 * A reason is required and a stale entry fails, so this cannot become the place
	 * the question quietly goes to die — the same contract
	 * ai_link_token_file_exemptions() carries one guard over.
	 *
	 * @return array<string,string>
	 */
	function ai_form_feature_exemptions(): array {

		return [
			// Pages have no per-form switch to read: the page editor renders the tag
			// chips and the Open Graph section unconditionally, which is why
			// aiResolveTagTarget reports a page as `tagging => true` rather than
			// special-casing the kind at each seam.
			"tagging PageService::performCreate" => "a page's tags; pages are always taggable",
			"tagging PageService::performUpdate" => "the same, on update",
			"tagging PageService::syncTags" => "the page-record primitive both of the above call",
			"open_graph PageService::performCreate" => "a page's Open Graph; the page editor always offers it",
			"open_graph PageService::performUpdate" => "the same, on update",
			"open_graph PageService::syncOpenGraph" => "the page-record primitive both of the above call",
			// Teardown removes the state rather than authoring it, so there is nothing
			// for a switch to make invisible.
			"tagging PageService::delete" => "teardown — clears a deleted page's relations",
			"open_graph PageService::delete" => "teardown — clears a deleted page's Open Graph row",
			"tagging TagService::delete" => "teardown — clears a deleted tag's relations site-wide",
			// Merge and rename move relations that already exist between tags; neither
			// can give a record a relation it did not already have.
			"tagging TagService::retargetTagRelations" => "merge/rename repoint existing relations; they create none",
			// The two AI seams the finding is actually about.
			"tagging TagService::aiRemoveTags" => "the deliberate cleanup path — discloses "
				. "through aiUntaggableFormNote instead of refusing, because it is the only surface that can "
				. "reach rows a tagging-off form hides",
			"tagging TagService::aiQueueTagChange" => "the queued half of aiAddTags/aiRemoveTags, reached only "
				. "through them; both resolve the gate before they call it",
			"tagging AutoModuleService::aiQueueEntryTagChange" => "the queue primitive underneath it, whose "
				. "only caller is aiQueueTagChange; the entry write seams reach the same state through "
				. "createItem/updateItem and gate it at aiFormRelationError",
		];
	}

	/** Every method in the services directory, as "Class::method" => body source. */
	function ai_feature_switch_method_bodies(): array {
		static $bodies = null;

		if ($bodies !== null) {

			return $bodies;
		}

		$root = SERVER_ROOT . "core/inc/bigtree/services/";
		$bodies = [];

		foreach (array_merge(
			glob($root . "*.php") ?: [],
			glob($root . "AI/*.php") ?: [],
			glob($root . "AI/Tools/*.php") ?: []
		) as $file) {
			$source = (string)file_get_contents($file);

			if (!preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $source, $match)) {

				continue;
			}

			$namespace = "BigTree\\Services\\";

			if (strpos($file, "/AI/Tools/") !== false) {
				$namespace .= "AI\\Tools\\";
			} elseif (strpos($file, "/AI/") !== false) {
				$namespace .= "AI\\";
			}

			$class = $namespace . $match[1];

			if (!class_exists($class)) {

				continue;
			}

			$reflection = new ReflectionClass($class);
			$lines = file($file);

			foreach ($reflection->getMethods() as $method) {
				if ($method->getDeclaringClass()->getName() !== $class) {

					continue;
				}

				$bodies[$reflection->getShortName() . "::" . $method->getName()] = implode("", array_slice(
					$lines,
					$method->getStartLine() - 1,
					$method->getEndLine() - $method->getStartLine() + 1
				));
			}
		}

		return $bodies;
	}

	/**
	 * E2, leg 1: every seam that writes a switched relation's state resolves the
	 * shared gate, or carries a reason.
	 *
	 * This is the leg that closes the class rather than the instance. `add_tags` was
	 * not missed for lack of a rule — `aiFormRelationError` already held the rule,
	 * and `aiModuleSchema` disclosed it to the model as if it were
	 * universally enforced. What was missing was anything that enumerated the writers.
	 */
	function test_every_writer_of_a_switched_relation_resolves_the_gate() {
		$bodies = ai_feature_switch_method_bodies();
		$exempt = ai_form_feature_exemptions();
		$found = [];
		$ungated = [];

		T::ok(count($bodies) > 400, "the sweep read the service methods (" . count($bodies) . ")");

		foreach (ai_form_feature_switches() as $switch => $rules) {
			foreach ($bodies as $seam => $body) {
				$writes = false;

				foreach ($rules["markers"] as $marker) {
					if (preg_match($marker, $body)) {
						$writes = true;

						break;
					}
				}

				if (!$writes) {

					continue;
				}

				$key = $switch . " " . $seam;
				$found[$key] = true;

				if (strpos($body, $rules["gate"]) === false && !isset($exempt[$key])) {
					$ungated[] = $key;
				}
			}
		}

		T::ok(count($found) > 8, "the sweep found the writers (" . count($found) . ")");
		T::ok(
			isset($found["tagging TagService::aiAddTags"]),
			"including add_tags"
		);
		T::equals(
			implode(", ", $ungated),
			"",
			"every writer of a switched relation resolves the shared gate, or says why it doesn't"
		);

		$stale = [];

		foreach (array_keys($exempt) as $key) {
			if (!isset($found[$key])) {
				$stale[] = $key;
			}
		}

		T::equals(implode(", ", $stale), "", "and no exemption names a seam that no longer writes one");
	}

	/**
	 * Mutating tools that take a switched relation as an *argument*, mapped to the
	 * validate and approval seams that must resolve its gate — the other direction of
	 * the same question, discovered from the real registry rather than listed.
	 *
	 * @return array<string,array{0:string,1:string}> tool => [validate, approval]
	 */
	function ai_form_feature_tool_seams(): array {

		return [
			"create_module_entry" => ["aiValidateEntryCreate", "aiCreateEntry"],
			"update_module_entry" => ["aiValidateEntryUpdate", "aiUpdateEntry"],
			"add_tags" => ["aiValidateAddTags", "aiAddTags"],
			// The disclosure half: gated on nothing, but it owes the sentence at both
			// staging and approval, which is the same two-seam obligation.
			"remove_tags" => ["aiValidateRemoveTags", "aiRemoveTags"],
			// Pages carry both relations as arguments and have no switch to read.
			"create_page" => ["aiValidatePageCreate", "aiCreatePage"],
			"update_page" => ["aiValidatePageUpdate", "aiUpdatePage"],
		];
	}

	/** The call each tool's two seams must name, or "" for a page tool. */
	function ai_form_feature_tool_gate(string $tool): string {
		if ($tool === "remove_tags") {

			// Either the note itself or the wrapper that folds it onto whichever branch
			// produced the result — both spellings are the disclosure being resolved.
			return "Untaggable";
		}

		if ($tool === "add_tags") {

			return "aiFormTaggingError(";
		}

		if (strpos($tool, "page") !== false) {

			return "";
		}

		return "aiFormRelationError(";
	}

	/**
	 * E2, leg 2: every mutating tool that offers a switched relation as an argument
	 * asks the switch at staging *and* at approval.
	 *
	 * Discovered from the registry's own parameter schemas, so a new mutating tool
	 * that grows a `tags` or `og_*` argument fails here until it is wired up. This is
	 * the leg that names `add_tags` directly: it declared `tags`, and neither of its
	 * seams asked anything about the form.
	 */
	function test_every_tool_offering_a_switched_relation_asks_the_switch() {
		$registry = ai_wiring_registry();
		$developer = (object)["id" => 1, "level" => 2, "permissions" => []];
		$declared = ai_form_feature_tool_seams();
		$mutating = ai_inverse_validate_seams();
		$in_scope = [];
		$unclassified = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$name = $tool->name();

			// Mutating tools only: the read side of tags is search_tags, which offers a
			// `tags`-shaped argument and writes nothing.
			if (!isset($mutating[$name])) {

				continue;
			}

			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];
			$relation = false;

			foreach (is_array($properties) ? array_keys($properties) : [] as $argument) {
				if ($argument === "tags" || strpos($argument, "og_") === 0) {
					$relation = true;

					break;
				}
			}

			if (!$relation) {

				continue;
			}

			$in_scope[] = $name;

			if (!isset($declared[$name])) {
				$unclassified[] = $name;
			}
		}

		sort($in_scope);
		T::ok(count($in_scope) >= 6, "the registry sweep found the relation-arguing tools (" . count($in_scope) . ")");
		T::equals(
			implode(", ", $unclassified),
			"",
			"every mutating tool that offers a switched relation names its two seams"
		);

		$stale = array_values(array_diff(array_keys($declared), $in_scope));
		T::equals(implode(", ", $stale), "", "and no declared tool has stopped offering one");

		$missing = [];

		foreach ($declared as $tool => [$validate, $approve]) {
			$gate = ai_form_feature_tool_gate($tool);

			if ($gate === "") {

				continue;
			}

			[$class] = $mutating[$tool];

			foreach (["staging" => $validate, "approval" => $approve] as $phase => $method) {
				$body = ai_surface_method_body($class, $method);
				T::ok($body !== "", "{$tool}: {$method}'s source was read");

				if (strpos($body, $gate) === false) {
					$missing[] = "{$tool} never asks the switch at {$phase} ({$method})";
				}
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"and each asks its switch at staging and again at approval"
		);
	}

	/**
	 * E3: a status column with no module action to flip it back is disclosed on the
	 * card, and a module that *has* the action is not — the positive control that
	 * keeps the row from becoming decorative.
	 */
	function test_set_module_entry_flag_discloses_a_column_no_admin_action_can_flip() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";

		try {
			[$module_id, $form_id, $table] = ai_feature_switch_module($suffix, [
				"columns" => ["archived"],
				// The table carries the column; the views carry no archive action. A
				// hand-built module, or a scaffolded one whose action was later removed
				// in the Module Designer, is in exactly this state.
				"view_actions" => ["edit" => "on", "delete" => "on"],
			]);
			$entry_id = (int)SQL::insert($table, ["title" => "ZZ Audit27 Unflippable"]);

			$svc = new AutoModuleService();
			$developer = (object)["id" => 0, "level" => 2, "permissions" => []];
			$args = [
				"module_id" => $module_id,
				"form" => $form_id,
				"entry_id" => (string)$entry_id,
				"flag" => "archived",
				"value" => true,
			];

			$staged = $svc->aiValidateEntryFlag($args, $developer);
			T::ok(!empty($staged["ok"]), "the flag change stages (" . (string)($staged["error"] ?? "") . ")");
			T::ok(
				isset($staged["preview"]["no_admin_action"]),
				"the card carries a row for the missing action"
			);
			T::ok(
				strpos((string)$staged["preview"]["no_admin_action"], "no archive action") !== false,
				"naming the action the module doesn't have"
			);
			T::ok(
				strpos((string)$staged["summary"], "only this assistant can") !== false,
				"and the summary the model reads back says who can change it back"
			);

			$applied = $svc->aiSetEntryFlag($staged["payload"], $developer);
			T::equals((string)($applied["mode"] ?? ""), "updated", "the change applies");
			T::ok(
				strpos((string)($applied["note"] ?? ""), "no archive action") !== false,
				"and the result repeats it, re-asked rather than carried from staging"
			);

			// Positive control: the same table and the same flag, with the action
			// declared on the view. Nothing to disclose.
			$module = BigTreeJSONDB::get("modules", $module_id);
			$module["views"][0]["actions"]["archive"] = "on";
			BigTreeJSONDB::update("modules", $module_id, $module);

			$restore = $svc->aiValidateEntryFlag(array_merge($args, ["value" => false]), $developer);
			T::ok(!empty($restore["ok"]), "un-archiving stages (" . (string)($restore["error"] ?? "") . ")");
			T::ok(
				!isset($restore["preview"]["no_admin_action"]),
				"a module whose views declare the action gets no such row"
			);
			T::ok(
				strpos((string)$restore["summary"], "only this assistant can") === false,
				"…and no such clause"
			);
			$restored = $svc->aiSetEntryFlag($restore["payload"], $developer);
			T::ok(!isset($restored["note"]), "nor a note on the result");
		} finally {
			ai_feature_switch_teardown($suffix, $table);
		}
	}
