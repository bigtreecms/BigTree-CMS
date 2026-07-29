<?php
	/**
	 * Audit #6 Phase 5 (Part E): the guards for the bug classes this pass found that
	 * no existing test could have caught.
	 *
	 * Audit #5's guards were structural in the weakest sense — they grepped a seam's
	 * source for `"fingerprint" =>` and for hand-written maps of tool names. Audit #6
	 * then found eight targeted mutations with no fingerprint at all (invisible to a
	 * hand-written map), two whose fingerprints evaluated to `[]` at runtime for the
	 * cases that mattered (invisible to a substring grep), and one that hashed the
	 * wrong thing entirely (invisible to both).
	 *
	 * So these derive their expectations from the *registry* and from the field-type
	 * *schemas* rather than from a list somebody has to remember to update, and they
	 * assert runtime values rather than the presence of a key.
	 */

	use BigTree\Api\Resources;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalFingerprint;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\Tools\AbstractMutatingTool;
	use BigTree\Services\FieldTypeService;

	/**
	 * Every mutating tool the registry offers a developer, by name.
	 *
	 * Derived rather than listed: a tool added without a fingerprint decision is
	 * exactly the drift this guards, and a hand-written list can't see it.
	 *
	 * @return list<string>
	 */
	function audit6_mutating_tool_names(): array {
		$names = [];

		foreach (ai_wiring_registry()->availableTools(ai_wiring_user(2)) as $tool) {
			if ($tool->kind() === "mutate") {
				$names[] = $tool->name();
			}
		}

		sort($names);

		return $names;
	}

	/**
	 * Mutating tools that address an *existing* record, mapped to the validate seam
	 * that must stage a fingerprint for it, and a representative payload.
	 *
	 * A create has no prior state to compare against, so it is exempt — but the
	 * exemption is declared here and cross-checked against the registry below, so a
	 * new tool can't quietly inherit it.
	 *
	 * @return array<string,array{0:string,1:string,2:array<string,mixed>}>
	 */
	function audit6_targeted_mutations(): array {

		return [
			"update_page" => [
				\BigTree\Services\PageService::class, "aiValidatePageUpdate",
				["id" => "%page%", "meta_description" => "zz audit6 fingerprint"],
			],
			"update_page_content" => [
				\BigTree\Services\PageService::class, "aiValidatePageContentUpdate",
				["id" => "%content_page%", "content" => "%content_values%"],
			],
			"archive_page" => [
				\BigTree\Services\PageService::class, "aiValidatePageArchive", ["id" => "%page_int%"],
			],
			"unarchive_page" => [
				\BigTree\Services\PageService::class, "aiValidatePageUnarchive", ["id" => "%archived_page_int%"],
			],
			"move_page" => [
				\BigTree\Services\PageService::class, "aiValidatePageMove",
				["id" => "%page_int%", "parent" => "%other_page_int%"],
			],
			"save_page_revision" => [
				\BigTree\Services\PageService::class, "aiValidateSaveRevision",
				["page_id" => "%page_int%", "description" => "zz audit6 snapshot"],
			],
			"add_tags" => [
				\BigTree\Services\TagService::class, "aiValidateAddTags",
				["page_id" => "%page_int%", "tags" => ["%tag%"]],
			],
			"remove_tags" => [
				\BigTree\Services\TagService::class, "aiValidateRemoveTags",
				["page_id" => "%page_int%", "tags" => ["%attached_tag%"]],
			],
			"merge_tags" => [
				\BigTree\Services\TagService::class, "aiValidateTagMerge",
				["from" => ["%tag%"], "into" => "%other_tag%"],
			],
			"rename_tag" => [
				\BigTree\Services\TagService::class, "aiValidateTagRename",
				["tag" => "%tag%", "name" => "zz audit6 renamed"],
			],
			"update_setting" => [
				\BigTree\Services\SettingService::class, "aiValidateSettingUpdate",
				["id" => "%setting%", "value" => "zz audit6 value"],
			],
			"update_user" => [
				\BigTree\Services\UserService::class, "aiValidateUserUpdate",
				["user_id" => "%user%", "name" => "ZZ Audit6 Renamed"],
			],
			"update_template" => [
				\BigTree\Services\TemplateService::class, "aiValidateTemplateUpdate",
				["id" => "%template%", "name" => "ZZ Audit6 Template"],
			],
			"update_callout" => [
				\BigTree\Services\CalloutService::class, "aiValidateCalloutUpdate",
				["id" => "%callout%", "name" => "ZZ Audit6 Callout"],
			],
			"update_module" => [
				\BigTree\Services\ModuleService::class, "aiValidateModuleUpdate",
				["module_id" => "%module%", "name" => "ZZ Audit6 Module"],
			],
			"update_module_entry" => [
				\BigTree\Services\AutoModuleService::class, "aiValidateEntryUpdate",
				["module_id" => "%module%", "entry_id" => "%entry%", "data" => ["title" => "zz audit6 entry"]],
			],
			"set_module_entry_flag" => [
				\BigTree\Services\AutoModuleService::class, "aiValidateEntryFlag",
				["module_id" => "%module%", "entry_id" => "%entry%", "flag" => "archived", "value" => true],
			],
			"delete_module_entry" => [
				\BigTree\Services\AutoModuleService::class, "aiValidateEntryDelete",
				["module_id" => "%module%", "entry_id" => "%entry%"],
			],
			"restore_page_revision" => [
				\BigTree\Services\PageService::class, "aiValidateRevisionRestore",
				["page_id" => "%page_int%", "revision_id" => "%revision%"],
			],
			"publish_pending_change" => [
				\BigTree\Services\PendingChangeService::class, "aiValidatePublishChange", ["change_id" => "%change%"],
			],
			"reject_pending_change" => [
				\BigTree\Services\PendingChangeService::class, "aiValidateRejectChange", ["change_id" => "%change%"],
			],
		];
	}

	/**
	 * Mutating tools with no existing target, and why. Every mutating tool must
	 * appear here or in audit6_targeted_mutations().
	 *
	 * @return array<string,string>
	 */
	function audit6_creation_tools(): array {

		return [
			"create_page" => "creates a page; there is no prior state to compare",
			"create_module_entry" => "creates an entry",
			"create_user" => "creates a user",
			"create_template" => "creates a template",
			"create_callout" => "creates a callout",
			"create_callout_group" => "creates a callout group",
			"create_module" => "creates a module",
			// The table it creates cannot exist yet — scaffoldPlan refuses an existing
			// one, at staging and again at approval — so there is no prior state.
			"scaffold_module" => "creates a module, its table, its form and its view",
			"create_module_group" => "creates a module group",
			"create_redirect" => "creates a 404 redirect; the source has no prior record to move",
		];
	}

	/**
	 * E1a: the fingerprint map can't drift from the registry.
	 *
	 * The audit-5 guard read a hand-written list, so the eight tools that staged no
	 * fingerprint at all were invisible to it — they simply weren't in the list.
	 * Enumerating from the registry means a tool has to be classified as targeted or
	 * creating before this passes, and the classification is the decision that was
	 * being skipped.
	 */
	function test_every_mutating_tool_is_classified_for_staleness() {
		$targeted = array_keys(audit6_targeted_mutations());
		$creating = array_keys(audit6_creation_tools());
		$classified = array_merge($targeted, $creating);
		$registered = audit6_mutating_tool_names();

		$unclassified = array_values(array_diff($registered, $classified));
		T::equals(
			implode(", ", $unclassified),
			"",
			"every mutating tool the registry offers is classified as targeted or creating"
		);

		$stale = array_values(array_diff($classified, $registered));
		T::equals(implode(", ", $stale), "", "no classification names a tool that no longer exists");
	}

	/**
	 * E1b: a targeted mutation's fingerprint must be non-empty *at runtime*.
	 *
	 * The audit-5 guard grepped for the literal `"fingerprint" =>`, which passed for
	 * update_setting (hashing the wrong record) and for the page and entry seams
	 * whose descriptor evaluated to `[]` for an Open Graph-only or tag-only edit.
	 * Staging each seam against a real fixture and computing the hash is the only
	 * check that can tell "there is a fingerprint" from "there is protection".
	 */
	function test_every_targeted_mutation_stages_a_usable_fingerprint() {
		if (!parity_db_available()) {
			return;
		}

		$fixtures = audit6_fingerprint_fixtures();

		if (!$fixtures) {
			echo "  (skipped — fixtures unavailable in this harness)\n";

			return;
		}

		$dev = (object)["id" => $fixtures["user_id"], "level" => 2, "permissions" => []];

		try {
			foreach (audit6_targeted_mutations() as $tool => [$class, $method, $args]) {
				$resolved = audit6_resolve_placeholders($args, $fixtures);

				if ($resolved === null) {
					echo "  (skipped {$tool} — fixture unavailable)\n";

					continue;
				}

				$validation = (new $class())->$method($resolved, $dev);

				// A seam that refuses this particular fixture has nothing to say about
				// fingerprints; only a successful staging is evidence either way.
				if (empty($validation["ok"])) {
					echo "  (skipped {$tool} — " . (string)($validation["error"] ?? $validation["denied"] ?? "not staged") . ")\n";

					continue;
				}

				$descriptor = $validation["fingerprint"] ?? null;

				T::ok(
					is_array($descriptor) && $descriptor,
					"{$tool} stages a staleness descriptor"
				);
				T::ok(
					ProposalFingerprint::compute($descriptor) !== "",
					"{$tool}'s descriptor computes to a non-empty hash"
				);
				T::equals(
					ProposalFingerprint::unsupportedReason($descriptor),
					null,
					"{$tool}'s descriptor is a shape the fingerprinter recognizes"
				);
			}
		} finally {
			audit6_clean_fingerprint_fixtures($fixtures);
		}
	}

	/**
	 * The other half of E1: a descriptor the fingerprinter *doesn't* recognize has
	 * to be loud.
	 *
	 * compute() answers "" both for "no staleness wanted" and for "I don't know what
	 * this is", and the second one is invisible — the same typo recurs at approval,
	 * so the hashes match and the check silently protects nothing. Staging refuses it
	 * instead.
	 */
	function test_a_malformed_fingerprint_descriptor_is_refused_at_staging() {
		T::equals(ProposalFingerprint::unsupportedReason([]), null, "no descriptor is a legitimate opt-out");
		T::equals(ProposalFingerprint::unsupportedReason(null), null, "and so is none at all");

		$bad = [
			"a misspelled type" => ["type" => "pge", "id" => 4, "columns" => ["title"]],
			"a page with no columns" => ["type" => "page", "id" => 4],
			"an entry with no table" => ["type" => "entry", "id" => "4", "columns" => ["title"]],
			"a tag set with no table" => ["type" => "tags", "id" => "4"],
			"an empty composite" => ["type" => "composite", "parts" => []],
			"a composite hiding a bad part" => ["type" => "composite", "parts" => [
				["type" => "page", "id" => 4, "columns" => ["title"]],
				["type" => "nonsense", "id" => 4],
			]],
		];

		foreach ($bad as $label => $descriptor) {
			T::ok(
				ProposalFingerprint::unsupportedReason($descriptor) !== null,
				"{$label} is reported as unusable"
			);
		}

		// And the refusal reaches the model as a failed tool call rather than a card.
		$tool = new Audit6BadFingerprintTool(new \BigTree\Services\AI\ProposalStore());
		$result = $tool->execute([], new \BigTree\Services\AI\AIToolContext(ai_wiring_user(2), 8));

		T::equals($result->type, AIToolResult::ERROR, "a tool staging a malformed descriptor errors");
		T::ok(
			stripos($result->toModelPayload()["error"] ?? "", "not a fingerprint type") !== false,
			"and the message names what was wrong with it"
		);
	}

	/**
	 * E2: field-id round trip. Covered in depth by ParityAIFieldIntegrityTest; this
	 * is the structural half — the seams must not transform an id at all.
	 */
	function test_field_id_seams_do_not_transform() {
		$body = ai_surface_method_body(Resources::class, "mergeAiFields")
			. ai_surface_method_body(Resources::class, "aiUnconfigurableFieldError");

		T::ok(
			strpos($body, "urlify") === false,
			"no field-id seam urlifies an id — that is what orphaned every underscore-id field's content"
		);
	}

	/**
	 * E5: required-settings coverage.
	 *
	 * For every field type the assistant may author, either it can express the
	 * type's schema-required settings, or the type is refused outright. A field type
	 * added later — core or from an extension — is covered without anyone updating a
	 * list, which is what left `list` unguarded after audit #5's hardcoded blocklist.
	 */
	function test_every_authorable_field_type_is_complete_or_refused() {
		foreach (["templates", "callouts"] as $use_case) {
			$surface = $use_case === "callouts" ? "callout" : "template";
			$types = FieldTypeService::availableFieldTypeIds($use_case);

			T::ok(count($types) > 0, "{$use_case} has field types to check");
			$refused = [];

			foreach ($types as $type) {
				$field = ["id" => "zz_audit6", "type" => $type, "title" => "ZZ Audit6"];
				$error = Resources::aiUnconfigurableFieldError([$field], [], $surface);

				if ($error !== null) {
					// Refused — that is a valid answer, and the one the assistant can
					// act on. Nothing more to check for this type.
					$refused[] = $type;

					continue;
				}

				// Allowed: then whatever the merge stores must satisfy every setting
				// the type's own schema marks required, or the editor will refuse to
				// save a record the assistant just created.
				$stored = Resources::mergeAiFields([$field], [], $surface);
				$settings = is_array($stored[0]["settings"] ?? null) ? $stored[0]["settings"] : [];
				$missing = [];

				foreach (FieldTypeService::settingsSchema($type) as $descriptor) {
					$id = (string)($descriptor["id"] ?? "");

					if ($id === "" || $id[0] === "_" || empty($descriptor["required"])) {

						continue;
					}

					if (($settings[$id] ?? "") === "") {
						$missing[] = $id;
					}
				}

				T::equals(
					implode(", ", $missing),
					"",
					"an AI-authored {$use_case} \"{$type}\" field is stored with its required settings"
				);
			}

			// The refusal arm has to be live, or this whole test passes vacuously the
			// moment the completeness rule stops firing — which is exactly how `list`
			// stayed authorable-but-empty through audit #5.
			T::ok(
				in_array("matrix", $refused, true) && in_array("one-to-many", $refused, true),
				"{$use_case}: the types that need structured configuration are still refused"
			);
			T::ok(
				in_array("list", $refused, true),
				"{$use_case}: a list field with no options is refused rather than stored empty"
			);

			// …and supplying the options is what lifts the refusal.
			T::equals(
				Resources::aiUnconfigurableFieldError(
					[["id" => "zz_audit6", "type" => "list", "title" => "ZZ", "options" => ["A", "B"]]],
					[],
					$surface
				),
				null,
				"{$use_case}: a list field with options is accepted"
			);
		}
	}

	/**
	 * E6: capability keys and declines derived from the catalog.
	 *
	 * Every decline line is spliced into the system prompt beside "do not improvise a
	 * workaround", so a line describing something the catalog implements is worse
	 * than no line at all: it argues the model out of a tool it has. And every key
	 * get_my_capabilities' own description promises has to exist.
	 */
	function test_capability_declines_do_not_contradict_the_catalog() {
		$out_of_scope = implode(" ", array_keys(CapabilitySummary::outOfScope()));
		$registered = [];

		foreach (ai_wiring_registry()->availableTools(ai_wiring_user(2)) as $tool) {
			$registered[] = $tool->name();
		}

		// Phrases whose plain meaning is a tool we ship. Each was, or could become,
		// a decline that contradicts the catalog.
		$contradictions = [
			"moving a module between groups" => "update_module",
			"creating a redirect" => "create_redirect",
			"merging tags" => "merge_tags",
			"renaming a tag" => "rename_tag",
			"restoring a page revision" => "restore_page_revision",
			"archiving a page" => "archive_page",
		];

		foreach ($contradictions as $phrase => $tool) {
			if (!in_array($tool, $registered, true)) {

				continue;
			}

			T::ok(
				stripos($out_of_scope, $phrase) === false,
				"outOfScope() does not decline \"{$phrase}\" while {$tool} implements it"
			);
		}

		// Every capability key the tool's description names must be present.
		$keys = CapabilitySummary::forUser(ai_wiring_user(2));

		foreach (["users", "tags", "settings", "templates", "modules", "callouts"] as $noun) {
			T::ok(
				array_key_exists("can_manage_{$noun}", $keys),
				"forUser() has the can_manage_{$noun} key get_my_capabilities promises"
			);
		}
	}

	/**
	 * E7: the extension-tool contract is enforced at dispatch, not documented.
	 *
	 * kind() is what the whole approval model rests on: a tool declaring "read" is
	 * run unattended. Nothing in production checked that a tool's *behavior* matched
	 * its declaration, so an extension could mutate mid-turn with no card, no
	 * approval, no audit row and no staleness check.
	 */
	function test_kind_is_enforced_when_a_tool_runs() {
		$registry = new \BigTree\Services\AI\AIToolRegistry();
		$registry->register(new Audit6LyingReadTool());
		$registry->register(new Audit6BadKindTool());
		$registry->register(new Audit6IdleMutatingTool());

		$context = new \BigTree\Services\AI\AIToolContext(ai_wiring_user(2), 8);

		$lying = $registry->execute("audit6_lying_read", [], $context);
		T::equals(
			$lying->toModelPayload()["status"],
			"error",
			"a tool declared \"read\" cannot stage a proposal"
		);

		$bad = $registry->execute("audit6_bad_kind", [], $context);
		T::equals($bad->toModelPayload()["status"], "error", "an unknown kind is refused");

		$idle = $registry->execute("audit6_idle_mutating", [], $context);
		T::equals(
			$idle->toModelPayload()["status"],
			"error",
			"a tool declared \"mutate\" cannot return a finished result instead of staging one"
		);
	}

	/**
	 * E8a: an empty model turn is an error, never a successful blank answer.
	 *
	 * A round that produced neither tool calls nor text used to break out of the
	 * loop and be persisted as the answer — a blank assistant bubble above a
	 * ProposalCard with nothing explaining what was being approved.
	 */
	function test_an_empty_turn_surfaces_as_an_error() {
		$ai = new Audit6EmptyAI();
		$loop = new \BigTree\Services\AI\AgentLoop($ai, new \BigTree\Services\AI\AIToolRegistry(), 3);
		$run = $loop->run(
			[["role" => "user", "content" => "hello"]],
			new \BigTree\Services\AI\AIToolContext(ai_wiring_user(2), 8),
			[]
		);

		T::equals($run["answer"], null, "an empty turn yields no answer");
		T::ok(is_string($run["error"]) && $run["error"] !== "", "and reports an error instead");
	}

	/**
	 * E8b: a truncated stream is an error, not an answer.
	 *
	 * chatStream returned the accumulator's result whenever curl reported a 2xx, so
	 * a connection dropped mid-stream handed back partial text as authoritative, and
	 * a tool call cut mid-arguments degraded to one with no arguments at all.
	 */
	function test_a_truncated_stream_is_incomplete() {
		$noop = function (string $chunk): void {};

		// A stream that ends without its terminal marker.
		$cut = new \BigTree\Services\AI\StreamAccumulator("openai", $noop);
		$cut->feedLine('data: {"choices":[{"delta":{"content":"Half a sen"}}]}');
		$cut->result();

		T::equals($cut->isComplete(), false, "a stream with no terminal marker is incomplete");
		T::ok($cut->incompleteReason() !== "", "and says why");

		// A stream that finished, but whose tool-call arguments were cut.
		$broken = new \BigTree\Services\AI\StreamAccumulator("openai", $noop);
		$broken->feedLine(
			'data: {"choices":[{"delta":{"tool_calls":[{"index":0,"id":"c1","function":'
				. '{"name":"update_page","arguments":"{\"id\":\"4"}}]}}]}'
		);
		$broken->feedLine("data: [DONE]");
		$broken->result();

		T::equals($broken->isComplete(), false, "truncated tool-call arguments make the turn incomplete");
		T::ok(
			strpos($broken->incompleteReason(), "tool call") !== false,
			"and the reason names the tool call rather than blaming the text"
		);

		// A complete stream is complete.
		$whole = new \BigTree\Services\AI\StreamAccumulator("openai", $noop);
		$whole->feedLine('data: {"choices":[{"delta":{"content":"Done."}}]}');
		$whole->feedLine("data: [DONE]");
		$whole->result();

		T::equals($whole->isComplete(), true, "a finished stream is complete");
	}

	// — fixtures —

	/**
	 * Build the records the fingerprint sweep addresses. Returns [] when the harness
	 * can't provide them.
	 *
	 * @return array<string,mixed>
	 */
	function audit6_fingerprint_fixtures(): array {
		$fixtures = [
			"page_id" => 0, "archived_page_id" => 0, "other_page_id" => 0, "user_id" => 0,
			"tag_id" => 0, "other_tag_id" => 0, "entry_id" => 0, "change_id" => 0, "setting_id" => "",
		];

		try {
			$fixtures["user_id"] = parity_seed_user(["level" => 2, "name" => "ZZ Audit6 Actor"]);
			$fixtures["page_id"] = parity_seed_page(["nav_title" => "ZZ Audit6 Fingerprint"]);
			$fixtures["archived_page_id"] = parity_seed_page([
				"nav_title" => "ZZ Audit6 Archived",
				"archived" => "on",
			]);
			$fixtures["tag_id"] = (int)SQL::insert("bigtree_tags", [
				"tag" => "zz audit6 tag",
				"route" => "zz-audit6-tag-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);
			$fixtures["other_tag_id"] = (int)SQL::insert("bigtree_tags", [
				"tag" => "zz audit6 other",
				"route" => "zz-audit6-other-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			// remove_tags needs a tag that is actually attached.
			SQL::insert("bigtree_tags_rel", [
				"table" => "bigtree_pages",
				"entry" => (string)$fixtures["page_id"],
				"tag" => $fixtures["tag_id"],
			]);

			$fixtures["other_page_id"] = parity_seed_page(["nav_title" => "ZZ Audit6 Destination"]);

			// A content-bearing page, so update_page_content has fields to change.
			$content = parity_ai_content_required();
			$fixtures["content_page_id"] = 0;
			$fixtures["content_values"] = [];

			if ($content) {
				$values = [];

				foreach ($content as $field) {
					$values[$field] = "zz audit6 body";
				}

				$fixtures["content_values"] = $values;
				$fixtures["content_page_id"] = parity_seed_page([
					"nav_title" => "ZZ Audit6 Content",
					"template" => "content",
					"resources" => json_encode($values),
				]);
			}

			// A live module entry, and a queued change against it.
			if (parity_ai_processors_ready()) {
				$service = new \BigTree\Services\AutoModuleService();
				$actor = (object)["id" => $fixtures["user_id"], "level" => 2, "permissions" => []];
				$created = parity_ai_create_entry($service, $actor, ["title" => "zz Audit6 Fingerprint Entry"]);
				$fixtures["entry_id"] = (int)$created["id"];

				if ($fixtures["entry_id"] > 0) {
					$fixtures["change_id"] = (int)SQL::insert("bigtree_pending_changes", [
						"user" => $fixtures["user_id"],
						"date" => "NOW()",
						"table" => "timber_news",
						"item_id" => $fixtures["entry_id"],
						"changes" => ["title" => "zz Audit6 Queued"],
						"mtm_changes" => [],
						"tags_changes" => [],
						"open_graph_changes" => [],
						"module" => parity_news_module_id(),
						"type" => "EDIT",
						"title" => "Entry Change Pending",
						"pending_page_parent" => 0,
					]);
				}
			}

			// A setting with a definition and a value row.
			$fixtures["setting_id"] = "zz-audit6-" . bin2hex(random_bytes(3));
			BigTreeJSONDB::insert("settings", [
				"id" => $fixtures["setting_id"],
				"name" => "ZZ Audit6 Setting",
				"description" => "",
				"type" => "text",
				"settings" => [],
				"locked" => "",
				"system" => "",
				"encrypted" => "",
				"extension" => null,
			]);
			SQL::insert("bigtree_settings", [
				"id" => $fixtures["setting_id"],
				"encrypted" => "",
				"value" => json_encode("before"),
			]);

			// The first template and callout that actually exist on this install.
			$templates = BigTreeJSONDB::getAll("templates");
			$fixtures["template_id"] = (string)($templates[0]["id"] ?? "");
			$callouts = BigTreeJSONDB::getAll("callouts");
			$fixtures["callout_id"] = (string)($callouts[0]["id"] ?? "");

			$fixtures["revision_id"] = (int)SQL::insert("bigtree_page_revisions", [
				"page" => $fixtures["page_id"],
				"title" => "ZZ Audit6 Older Title",
				"meta_description" => "",
				"template" => "",
				"external" => "",
				"new_window" => "",
				"resources" => "",
				"author" => $fixtures["user_id"],
				"saved" => "",
				"saved_description" => "",
				"resource_allocation" => "",
				"has_deleted_resources" => "",
			]);
		} catch (Throwable $e) {
			echo "  (fixture setup failed: " . $e->getMessage() . ")\n";
		}

		return $fixtures;
	}

	/** @param array<string,mixed> $fixtures */
	function audit6_clean_fingerprint_fixtures(array $fixtures): void {
		SQL::query(
			"DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?",
			(string)($fixtures["page_id"] ?? 0)
		);
		parity_delete_page((int)($fixtures["page_id"] ?? 0));
		parity_delete_page((int)($fixtures["archived_page_id"] ?? 0));
		parity_delete_page((int)($fixtures["other_page_id"] ?? 0));
		parity_delete_page((int)($fixtures["content_page_id"] ?? 0));
		parity_delete_pending((int)($fixtures["change_id"] ?? 0));
		parity_delete_news_entries((int)($fixtures["entry_id"] ?? 0));
		parity_delete_tags((int)($fixtures["tag_id"] ?? 0), (int)($fixtures["other_tag_id"] ?? 0));
		parity_delete_users((int)($fixtures["user_id"] ?? 0));

		$setting_id = (string)($fixtures["setting_id"] ?? "");

		if ($setting_id !== "") {
			BigTreeJSONDB::delete("settings", $setting_id);
			SQL::delete("bigtree_settings", $setting_id);
		}
	}

	/**
	 * Substitute the %placeholders% in a tool's representative arguments. Returns
	 * null when the fixture the tool needs isn't available in this harness — the
	 * sweep skips those rather than reporting a false failure.
	 *
	 * @param array<string,mixed> $args
	 * @param array<string,mixed> $fixtures
	 * @return array<string,mixed>|null
	 */
	function audit6_resolve_placeholders(array $args, array $fixtures): ?array {
		$map = [
			"%page%" => (string)$fixtures["page_id"],
			"%page_int%" => (int)$fixtures["page_id"],
			"%other_page_int%" => (int)($fixtures["other_page_id"] ?? 0),
			"%archived_page_int%" => (int)$fixtures["archived_page_id"],
			"%content_page%" => (string)($fixtures["content_page_id"] ?? 0),
			"%content_values%" => is_array($fixtures["content_values"] ?? null) ? $fixtures["content_values"] : [],
			"%user%" => (int)$fixtures["user_id"],
			"%tag%" => "zz audit6 tag",
			"%attached_tag%" => "zz audit6 tag",
			"%other_tag%" => "zz audit6 other",
			"%revision%" => (int)($fixtures["revision_id"] ?? 0),
			"%module%" => parity_news_module_id(),
			"%entry%" => (string)($fixtures["entry_id"] ?? 0),
			"%change%" => (int)($fixtures["change_id"] ?? 0),
			"%setting%" => (string)($fixtures["setting_id"] ?? ""),
			"%template%" => (string)($fixtures["template_id"] ?? ""),
			"%callout%" => (string)($fixtures["callout_id"] ?? ""),
		];

		$out = [];

		foreach ($args as $key => $value) {
			if (is_array($value)) {
				$nested = audit6_resolve_placeholders($value, $fixtures);

				if ($nested === null) {

					return null;
				}

				$out[$key] = $nested;

				continue;
			}

			if (is_string($value) && array_key_exists($value, $map)) {
				$resolved_value = $map[$value];

				// A placeholder that resolved to nothing means the harness couldn't
				// seed that fixture; skip rather than validate against id 0.
				if ($resolved_value === "" || $resolved_value === 0 || $resolved_value === []) {

					return null;
				}

				$out[$key] = $resolved_value;

				continue;
			}

			// A placeholder with no fixture behind it (a module, entry, template,
			// callout, setting or pending change this harness doesn't seed).
			if (is_string($value) && strlen($value) > 2 && $value[0] === "%" && substr($value, -1) === "%") {

				return null;
			}

			$out[$key] = $value;
		}

		return $out;
	}

	/** A tool that claims to be a read but stages a proposal. */
	class Audit6LyingReadTool implements \BigTree\Services\AI\AIToolInterface {
		public function name(): string { return "audit6_lying_read"; }
		public function kind(): string { return "read"; }
		public function isAvailable($user): bool { return true; }
		public function definition($user): array { return ["type" => "function", "function" => ["name" => $this->name()]]; }

		public function execute(array $args, \BigTree\Services\AI\AIToolContext $context): AIToolResult {

			return AIToolResult::proposal("I already did it", [], "fake");
		}
	}

	/** A tool whose kind() is a typo — outside every rule keyed on it. */
	class Audit6BadKindTool implements \BigTree\Services\AI\AIToolInterface {
		public function name(): string { return "audit6_bad_kind"; }
		public function kind(): string { return "mutating"; }
		public function isAvailable($user): bool { return true; }
		public function definition($user): array { return ["type" => "function", "function" => ["name" => $this->name()]]; }

		public function execute(array $args, \BigTree\Services\AI\AIToolContext $context): AIToolResult {

			return AIToolResult::ok([]);
		}
	}

	/** A mutating tool that returns a finished result instead of staging one. */
	class Audit6IdleMutatingTool implements \BigTree\Services\AI\AIToolInterface {
		public function name(): string { return "audit6_idle_mutating"; }
		public function kind(): string { return "mutate"; }
		public function isAvailable($user): bool { return true; }
		public function definition($user): array { return ["type" => "function", "function" => ["name" => $this->name()]]; }

		public function execute(array $args, \BigTree\Services\AI\AIToolContext $context): AIToolResult {

			return AIToolResult::ok(["done" => true]);
		}
	}

	/**
	 * A mutating tool whose backend hands up a staleness descriptor the fingerprinter
	 * doesn't recognize — the shape of a typo in a real seam.
	 */
	class Audit6BadFingerprintTool extends AbstractMutatingTool {
		public function name(): string { return "audit6_bad_fingerprint"; }
		public function definition($user): array { return ["type" => "function", "function" => ["name" => $this->name()]]; }

		public function execute(array $args, \BigTree\Services\AI\AIToolContext $context): AIToolResult {

			return $this->stageFromValidation([
				"ok" => true,
				"summary" => "Change something.",
				"preview" => [],
				"payload" => [],
				"fingerprint" => ["type" => "nonsense", "id" => 1],
			], $context, $this->name());
		}
	}

	/** A provider that always answers with nothing at all. */
	class Audit6EmptyAI extends BigTreeAI {
		public function __construct() {}

		public function chat($messages, $tools = [], $options = []) {

			return ["content" => null, "tool_calls" => [], "raw" => []];
		}
	}
