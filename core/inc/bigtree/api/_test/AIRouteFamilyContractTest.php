<?php
	/**
	 * Audit #4 (C3): the parity-drift guard.
	 *
	 * Four audits in a row found route families that had gained endpoints without
	 * gaining either a tool or a decline line — the whole of audit #4's part B was
	 * this one category. The failure is silent by nature: the model discovers the
	 * wall by failing at it mid-conversation, and nothing in the suite notices.
	 *
	 * So every endpoint family in core/inc/bigtree/api/routes/*.php must be
	 * classified here as exactly one of:
	 *
	 *   - COVERED — a registered tool provides it (the tool must actually exist)
	 *   - DECLINED — outOfScope() names it (the wording must actually be there)
	 *   - N/A — plumbing no assistant would ever drive (auth, locks, openapi, ai)
	 *
	 * A new endpoint family fails this test until someone makes the tool-or-decline
	 * decision explicitly. That is the point: the decision is cheap to make and
	 * expensive to discover later.
	 */

	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Every endpoint family declared in the route files, as family => list of
	 * "METHOD /path". A family is the first path segment plus the first following
	 * literal (non-placeholder) segment, which is the granularity the audits
	 * reason in ("pages/revisions", "system/configure").
	 *
	 * @return array<string,list<string>>
	 */
	function ai_contract_route_families(): array {
		static $families = null;

		if ($families !== null) {

			return $families;
		}

		$families = [];

		foreach (glob(SERVER_ROOT . "core/inc/bigtree/api/routes/*.php") ?: [] as $file) {
			preg_match_all(
				'/"(GET|POST|PUT|PATCH|DELETE) ([^"]+)"\s*=>/',
				(string)file_get_contents($file),
				$matches,
				PREG_SET_ORDER
			);

			foreach ($matches as $match) {
				$segments = array_values(array_filter(explode("/", $match[2])));
				$family = $segments[0] ?? "";

				// Skip {id}-style placeholders when picking the qualifying segment, so
				// "pages/{id}/revisions" buckets with "pages/revisions".
				foreach (array_slice($segments, 1, 2) as $segment) {
					if (strpos($segment, "{") === false) {
						$family .= "/" . $segment;

						break;
					}
				}

				$families[$family][] = $match[1] . " " . $match[2];
			}
		}

		ksort($families);

		return $families;
	}

	/** Tool names a developer sees in the real chat registry. */
	function ai_contract_tool_names(): array {
		static $names = null;

		if ($names === null) {
			$service = new AIChatService();
			$build = new ReflectionMethod($service, "buildRegistry");
			$build->setAccessible(true);
			$registry = $build->invoke($service, new ProposalStore());
			$developer = (object)["id" => 1, "level" => 2, "permissions" => []];
			$names = [];

			foreach ($registry->availableTools($developer) as $tool) {
				$names[] = $tool->name();
			}
		}

		return $names;
	}

	/**
	 * family => the tool that covers it, or a per-verb map when the family has both
	 * read and write endpoints.
	 *
	 * The per-verb form exists because a single tool name hid a real gap: `callouts`
	 * and `modules/entries` were both marked COVERED by a *write* tool while their
	 * read endpoints (`GET /callouts`, `GET /modules/{id}/entries`) had no tool at
	 * all — the model could create a callout but never list one, and could edit an
	 * entry but never list a module's entries. Naming the tool per verb makes that
	 * kind of hole fail the test instead of hiding behind a create tool.
	 *
	 * A verb whose endpoint is deliberately unavailable takes a "declined: <phrase>"
	 * value, checked against outOfScope() exactly as ai_contract_declined() is.
	 */
	function ai_contract_covered(): array {

		return [
			"404s/redirect" => "create_redirect",
			"dashboard/content-alerts" => "get_content_alerts",
			"callouts" => [
				"GET" => "list_callouts",
				"POST" => "create_callout",
				"PATCH" => "update_callout",
				"DELETE" => "declined: deleting users, templates, callouts, modules or settings",
			],
			"callout-groups" => [
				"GET" => "list_callouts",
				"POST" => "create_callout_group",
				"PATCH" => "declined: renaming, reordering or deleting module and callout groups",
				"DELETE" => "declined: renaming, reordering or deleting module and callout groups",
			],
			"module-groups" => [
				"GET" => "get_module",
				"POST" => "create_module_group",
				"PATCH" => "declined: renaming, reordering or deleting module and callout groups",
				"DELETE" => "declined: renaming, reordering or deleting module and callout groups",
			],
			"modules" => [
				"GET" => "get_module",
				"POST" => "create_module",
				"PATCH" => "update_module",
				"DELETE" => "declined: deleting users, templates, callouts, modules or settings",
			],
			"modules/entries" => [
				"GET" => "list_module_entries",
				"POST" => "create_module_entry",
				"PATCH" => "update_module_entry",
				"DELETE" => "delete_module_entry",
			],
			"pages" => "create_page",
			"pages/archive" => "archive_page",
			"pages/unarchive" => "unarchive_page",
			"pages/move" => "move_page",
			"pages/pending" => "get_page",
			"pages/revisions" => "get_page_revisions",
			"pages/search" => "search_pages",
			"pages/seo-rating" => "get_page_seo_rating",
			"pending-changes" => "get_pending_changes",
			"pending-changes/approve" => "publish_pending_change",
			"pending-changes/reject" => "reject_pending_change",
			"resources/search" => "search_files",
			"search" => "search_pages",
			"search/ai" => "semantic_search",
			"settings" => "update_setting",
			"tags" => "add_tags",
			"tags/merge" => "merge_tags",
			"tags/search" => "search_tags",
			"templates" => [
				"GET" => "list_templates",
				"POST" => "create_template",
				"PATCH" => "update_template",
				"DELETE" => "declined: deleting users, templates, callouts, modules or settings",
			],
			"users" => "create_user",
			"audit" => "get_audit_trail",
			"audit/tables" => "get_audit_trail",
			"db/tables" => "get_module_schema",
			"modules/forms" => "get_module_schema",
		];
	}

	/**
	 * family => a phrase that must appear in an outOfScope() key. Lower-cased
	 * substring match, so the assertion survives rewording but not removal.
	 */
	function ai_contract_declined(): array {

		return [
			"404s" => "404 monitoring",
			"404s/bulk-delete" => "404 monitoring",
			"404s/clear-dead" => "404 monitoring",
			"404s/export" => "404 monitoring",
			"404s/ignore" => "404 monitoring",
			"404s/import" => "404 monitoring",
			"callouts/reorder" => "reordering templates, callouts or modules",
			"templates/reorder" => "reordering templates, callouts or modules",
			"modules/reorder" => "reordering templates, callouts or modules",
			"pages/reorder" => "duplicating or reordering pages",
			"pages/duplicate" => "duplicating or reordering pages",
			"modules/scaffold" => "scaffolding",
			"modules/actions" => "tables, forms, views or actions",
			"modules/views" => "tables, forms, views or actions",
			// Audit #5: the "editing …" line says nothing about *running* a report,
			// which is a module-view-level read any editor has — so the family needs
			// its own decline wording, not the editing one.
			"modules/reports" => "running or exporting a module's reports",
			"modules/gbp-categories" => "tables, forms, views or actions",
			"modules/embed-forms" => "embedded forms",
			"embed-forms" => "embedded forms",
			"embed-forms/submit" => "embedded forms",
			"dashboard/integrity" => "integrity scans",
			"dashboard/analytics" => "configuring analytics",
			"extensions" => "extensions",
			"extensions/build" => "extensions",
			"extensions/install" => "extensions",
			"extensions/recache-hooks" => "extensions",
			"extensions/updates" => "extensions",
			"extensions/upgrade" => "extensions",
			"feeds" => "feeds",
			"field-types" => "custom field types",
			"field-types/render" => "custom field types",
			"field-types/schema" => "custom field types",
			"images/crop" => "uploading or managing files",
			"images/process" => "uploading or managing files",
			"images/reprocess" => "uploading or managing files",
			"messages" => "internal messages",
			"messages/read" => "internal messages",
			"messages/unread-count" => "internal messages",
			"resources" => "uploading or managing files",
			"resources/allocations" => "uploading or managing files",
			"resources/crop" => "uploading or managing files",
			"resources/metadata-fields" => "uploading or managing files",
			"resources/replace" => "uploading or managing files",
			"resources/upload" => "uploading or managing files",
			"resources/usage" => "uploading or managing files",
			"resources/video" => "uploading or managing files",
			"resource-folders" => "resource folders",
			"resource-folders/flat" => "resource folders",
			"system/backup" => "system maintenance",
			"system/bans" => "system maintenance",
			"system/cache" => "system maintenance",
			"system/security-policy" => "system maintenance",
			"system/upgrade" => "system maintenance",
			"system/configure" => "configuring integrations",
			"users/2fa" => "two-factor authentication",
			"auth/2fa" => "two-factor authentication",
			"auth/passkey" => "two-factor authentication",
			"auth/passkeys" => "two-factor authentication",
			"users/password" => "levels, permissions or passwords",
		];
	}

	/** family => why no assistant would ever drive it. */
	function ai_contract_not_applicable(): array {

		return [
			"ai/chat" => "the assistant itself",
			"ai/conversations" => "the assistant itself",
			"ai/proposals" => "the assistant itself",
			"auth/emulate" => "session/authentication plumbing",
			"auth/forgot-password" => "session/authentication plumbing",
			"auth/login" => "session/authentication plumbing",
			"auth/login-policy" => "session/authentication plumbing",
			"auth/logout" => "session/authentication plumbing",
			"auth/logout-all" => "session/authentication plumbing",
			"auth/me" => "session/authentication plumbing",
			"auth/php-session" => "session/authentication plumbing",
			"auth/refresh" => "session/authentication plumbing",
			"auth/reset-password" => "session/authentication plumbing",
			"users/me" => "the acting user's own profile — get_my_capabilities covers what the model needs",
			// The assistant reads locks (ContentLock puts "someone else has this open"
			// on the proposal card) but never holds one: a lock belongs to an editing
			// session, and a chat turn has none to tie its lifetime to.
			"locks" => "content locks — read on the proposal card, but held by the editing UI, not by a chat turn",
			"locks/refresh" => "content locks — read on the proposal card, but held by the editing UI, not by a chat turn",
			"openapi.json" => "the API's own schema document",
			"dashboard/summary" => "an SPA render aggregate with no single capability behind it",
			"pages/access-levels" => "a permission read the tools resolve server-side themselves",
			"pages/sites" => "multi-site structure, deliberately dev-only",
			"system/site" => "multi-site structure, deliberately dev-only",
			"system/status" => "an SPA render aggregate with no single capability behind it",
			"system/version" => "an SPA render aggregate with no single capability behind it",
		];
	}

	function test_every_route_family_has_a_tool_a_decline_or_a_reason() {
		$families = ai_contract_route_families();
		$covered = ai_contract_covered();
		$declined = ai_contract_declined();
		$not_applicable = ai_contract_not_applicable();

		T::ok(count($families) > 50, "route families were discovered");

		$unclassified = [];
		$double = [];

		foreach (array_keys($families) as $family) {
			$hits = (int)isset($covered[$family]) + (int)isset($declined[$family]) + (int)isset($not_applicable[$family]);

			if ($hits === 0) {
				$unclassified[] = $family;
			} elseif ($hits > 1) {
				$double[] = $family;
			}
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every route family is covered by a tool, named in outOfScope(), or explicitly N/A"
		);
		T::equals(implode(", ", $double), "", "no family is classified two ways at once");
	}

	/**
	 * A per-verb coverage map must actually cover every verb the family declares.
	 *
	 * Without this, adding `GET /callouts/{id}/instances` to a family whose map only
	 * names GET/POST/PATCH/DELETE would still pass — the map would just be silently
	 * incomplete, which is the same failure the family map itself exists to prevent,
	 * one level down.
	 */
	function test_per_verb_coverage_maps_name_every_verb() {
		$families = ai_contract_route_families();
		$gaps = [];
		$stale = [];

		foreach (ai_contract_covered() as $family => $coverage) {
			if (!is_array($coverage)) {

				continue;
			}

			$verbs = [];

			foreach ($families[$family] ?? [] as $endpoint) {
				$verbs[] = strtok($endpoint, " ");
			}

			$verbs = array_values(array_unique($verbs));

			foreach ($verbs as $verb) {
				if (!isset($coverage[$verb])) {
					$gaps[] = "{$family} {$verb}";
				}
			}

			foreach (array_keys($coverage) as $verb) {
				if (!in_array($verb, $verbs, true)) {
					$stale[] = "{$family} {$verb}";
				}
			}
		}

		T::equals(implode(", ", $gaps), "", "every verb of a per-verb family names a tool or a decline");
		T::equals(implode(", ", $stale), "", "no per-verb entry names a verb the family no longer declares");
	}

	function test_route_family_classifications_are_not_stale() {
		$families = ai_contract_route_families();
		$tools = ai_contract_tool_names();
		$declines = strtolower(implode(" | ", array_keys(CapabilitySummary::outOfScope())));

		// A classification pointing at a tool that no longer exists, or wording that
		// has been removed from the declines list, is worse than no classification —
		// it reads as a decision that was made and is silently no longer true.
		foreach (ai_contract_covered() as $family => $coverage) {
			foreach (is_array($coverage) ? $coverage : ["*" => $coverage] as $verb => $tool) {
				if (strpos($tool, "declined:") === 0) {
					$phrase = trim(substr($tool, strlen("declined:")));
					T::ok(
						strpos($declines, $phrase) !== false,
						"{$family} {$verb} is declined by wording matching \"{$phrase}\""
					);

					continue;
				}

				T::ok(in_array($tool, $tools, true), "{$family} {$verb} is covered by the registered tool {$tool}");
			}
		}

		foreach (ai_contract_declined() as $family => $phrase) {
			T::ok(strpos($declines, $phrase) !== false, "{$family} is declined by wording matching \"{$phrase}\"");
		}

		// And nothing may be classified that isn't a real family any more.
		$stale = [];

		foreach (array_merge(ai_contract_covered(), ai_contract_declined(), ai_contract_not_applicable()) as $family => $_) {
			if (!isset($families[$family])) {
				$stale[] = $family;
			}
		}

		T::equals(implode(", ", $stale), "", "no classification names a route family that no longer exists");
	}
