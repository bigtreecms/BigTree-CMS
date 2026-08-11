<?php
	/**
	 * Audit #5 Phase 5: the guards that should make pass #6 shorter.
	 *
	 * Audits 1–5 kept finding the same two shapes of drift, one tool at a time:
	 *
	 *  - a field the assistant can *write* that no read tool returns, so every edit
	 *    to it is proposed blind (audit #4's B4, audit #5's C3);
	 *  - a staged proposal whose target can move before approval, with nothing
	 *    checking (audit #5's B2, B3 and A1 together).
	 *
	 * Both are structural, so both can be guarded structurally. These are
	 * source-and-definition level checks: they don't prove a seam behaves correctly
	 * (the parity suites do that), they prove nobody added a surface without
	 * deciding how it is read and how it is re-checked.
	 */

	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Where each argument of each mutating tool can be read back.
	 *
	 * The value is either the read tool that returns it, or an explicit exemption
	 * with the reason. Adding a settable field without deciding which of the two it
	 * is fails `test_every_writable_field_is_readable_or_exempt` — which is the whole
	 * point: the decision is cheap now and invisible later.
	 *
	 * @return array<string,array<string,string>> tool => argument => read tool | "exempt: reason"
	 */
	function ai_surface_readable_by(): array {

		return [
			"create_page" => [
				"parent" => "get_page_tree", "nav_title" => "get_page", "title" => "get_page",
				"route" => "get_page", "template" => "get_page", "external" => "get_page",
				"new_window" => "get_page", "in_nav" => "get_page", "meta_description" => "get_page",
				"meta_keywords" => "get_page", "seo_invisible" => "get_page", "publish_at" => "get_page",
				"expire_at" => "get_page", "max_age" => "get_page", "og_title" => "get_page",
				"og_description" => "get_page", "og_type" => "get_page",
				"og_image" => "get_page", "tags" => "get_page",
				"content" => "get_page",
				"save_as_draft" => "exempt: a routing flag for this write, not a stored field",
			],
			"update_page" => [
				"id" => "get_page", "nav_title" => "get_page", "title" => "get_page", "route" => "get_page",
				"template" => "get_page", "external" => "get_page", "new_window" => "get_page",
				"in_nav" => "get_page", "meta_description" => "get_page", "meta_keywords" => "get_page",
				"seo_invisible" => "get_page", "publish_at" => "get_page", "expire_at" => "get_page",
				"max_age" => "get_page", "og_title" => "get_page", "og_description" => "get_page",
				"og_type" => "get_page", "og_image" => "get_page",
				"save_as_draft" => "exempt: a routing flag for this write, not a stored field",
			],
			"update_page_content" => [
				"id" => "get_page", "template" => "get_page", "content" => "get_page",
				"save_as_draft" => "exempt: a routing flag for this write, not a stored field",
			],
			"archive_page" => ["id" => "get_page"],
			"unarchive_page" => ["id" => "get_page"],
			"move_page" => ["id" => "get_page", "parent" => "get_page_tree"],
			"save_page_revision" => [
				"page_id" => "get_page",
				"description" => "get_page_revisions",
			],
			"restore_page_revision" => ["page_id" => "get_page", "revision_id" => "get_page_revisions"],
			"create_module_entry" => [
				"module_id" => "search_modules", "form" => "get_module_schema", "data" => "get_module_entry",
				"tags" => "get_module_entry", "og_title" => "get_module_entry",
				"og_description" => "get_module_entry", "og_type" => "get_module_entry",
				"og_image" => "get_module_entry",
				"save_as_draft" => "exempt: a routing flag for this write, not a stored field",
			],
			"update_module_entry" => [
				"module_id" => "search_modules", "form" => "get_module_schema", "entry_id" => "get_module_entry",
				"data" => "get_module_entry", "og_title" => "get_module_entry",
				"og_description" => "get_module_entry", "og_type" => "get_module_entry",
				"og_image" => "get_module_entry",
				"save_as_draft" => "exempt: a routing flag for this write, not a stored field",
			],
			"set_module_entry_flag" => [
				"module_id" => "search_modules", "form" => "get_module_schema",
				"entry_id" => "get_module_entry", "flag" => "get_module_entry", "value" => "get_module_entry",
			],
			"delete_module_entry" => [
				"module_id" => "search_modules", "form" => "get_module_schema", "entry_id" => "get_module_entry",
			],
			"add_tags" => [
				"page_id" => "get_page", "module_id" => "search_modules", "entry_id" => "get_module_entry",
				"form" => "get_module_schema", "tags" => "get_page",
			],
			"remove_tags" => [
				"page_id" => "get_page", "module_id" => "search_modules", "entry_id" => "get_module_entry",
				"form" => "get_module_schema", "tags" => "get_page",
			],
			"merge_tags" => ["into" => "search_tags", "from" => "search_tags"],
			"rename_tag" => ["tag" => "search_tags", "name" => "search_tags"],
			"publish_pending_change" => ["change_id" => "get_pending_change"],
			"reject_pending_change" => ["change_id" => "get_pending_change"],
			"update_setting" => ["id" => "get_settings", "value" => "get_settings"],
			"create_user" => [
				"email" => "search_users", "name" => "search_users", "company" => "search_users",
				"timezone" => "search_users",
				"daily_digest" => "search_users", "alerts" => "get_content_alerts",
			],
			// Audit #14 B1/B2: update_user is offered at every level now, because a
			// user's own profile is theirs to edit — and search_users is
			// administrator-gated, so for an editor it was no read surface at all.
			// get_my_capabilities carries the caller's own profile, which is what makes
			// every one of these readable at the level that can now write it.
			"update_user" => [
				"user_id" => "get_my_capabilities", "email" => "get_my_capabilities",
				"name" => "get_my_capabilities", "company" => "get_my_capabilities",
				"timezone" => "get_my_capabilities", "daily_digest" => "get_my_capabilities",
				// The subscription list itself, named page by page rather than counted.
				"alerts" => "get_content_alerts",
			],
			"create_redirect" => [
				"from" => "exempt: 404s have no read tool; the decline line covers managing the log",
				"to" => "exempt: 404s have no read tool; the decline line covers managing the log",
				"site_key" => "exempt: 404s have no read tool; the decline line covers managing the log",
			],
			"create_template" => [
				"id" => "get_template", "name" => "get_template", "level" => "get_template",
				"routed" => "get_template", "fields" => "get_template",
			],
			"update_template" => [
				"id" => "get_template", "name" => "get_template", "level" => "get_template",
				"fields" => "get_template",
			],
			"create_callout" => [
				"id" => "get_callout", "name" => "get_callout", "description" => "get_callout",
				"level" => "get_callout", "display_field" => "get_callout", "display_default" => "get_callout",
				"fields" => "get_callout", "group" => "get_callout",
			],
			"update_callout" => [
				"id" => "get_callout", "name" => "get_callout", "description" => "get_callout",
				"level" => "get_callout", "display_field" => "get_callout", "display_default" => "get_callout",
				"fields" => "get_callout", "group" => "get_callout",
			],
			"create_callout_group" => ["name" => "list_callouts", "callouts" => "list_callouts"],
			"create_module" => [
				"id" => "get_module", "name" => "get_module", "route" => "get_module", "class" => "get_module",
				// No `developer_only`: this map entry named an argument no tool declares
				// and no table stores. `readable_by` only fails on *undeclared*
				// arguments, so a stale entry lives forever (audit #18 B1).
				"icon" => "get_module", "group" => "get_module",
			],
			// Audit #11 C1. Everything a scaffold sets is readable afterwards through
			// get_module (the record) and get_module_schema (the form it builds), which
			// is the point: a scaffolded module is a module the assistant can then use.
			// The four shape arguments are exempt for the same reason `id` on a create
			// is: they describe what to build, and once built the built thing is what
			// is read back.
			"scaffold_module" => [
				"name" => "get_module", "route" => "get_module", "group" => "get_module",
				"icon" => "get_module", "table" => "get_module_schema", "fields" => "get_module_schema",
				// Audit #12 A5/D6: two per-form switches the scaffold sets, reported on
				// the schema alongside the fields — an entry write to either is refused
				// on a form that lacks it, so the model has to be able to read them.
				"tagging" => "get_module_schema", "open_graph" => "get_module_schema",
				"view_type" => "exempt: a landing-view shape, and views are the Module Designer's surface",
				"item_title" => "exempt: the form's own title, set once at build time",
				"view_title" => "exempt: the landing view's own title, set once at build time",
				"actions" => "exempt: which status columns to build; the flags themselves read back "
					. "through get_module_entry",
			],
			"update_module" => [
				"module_id" => "get_module", "name" => "get_module", "icon" => "get_module",
				"group" => "get_module", "class" => "get_module",
			],
			"create_module_group" => ["name" => "get_module"],
		];
	}

	/**
	 * Audit #9 E3: create/update tool pairs, and the arguments only one of them has.
	 *
	 * B1 (`class` settable on create_module, not on update_module) and B3
	 * (`daily_digest`/`alerts` settable on update_user, not on create_user) are the
	 * same shape in both directions, and both were found by reading route files. The
	 * pairing is knowable without one: for every create/update pair, an argument one
	 * declares and the other doesn't is either deliberate — and then someone can say
	 * why — or a hole the model discovers by failing.
	 *
	 * Every entry is `create-only: …` / `update-only: …` with the reason. An
	 * unexplained difference fails.
	 *
	 * @return array<string,array{0:string,1:string,2:array<string,string>}>
	 */
	function ai_surface_create_update_pairs(): array {

		return [
			"page" => ["create_page", "update_page", [
				"parent" => "create-only: a page's location is chosen when it is created; move_page changes it after",
				"content" => "create-only: update_page_content owns content edits, with its own fingerprint and lock",
				"tags" => "create-only: add_tags and remove_tags own tag edits on an existing page",
				"id" => "update-only: names the page being edited",
			]],
			"module_entry" => ["create_module_entry", "update_module_entry", [
				"tags" => "create-only: add_tags and remove_tags own tag edits on an existing entry",
				"entry_id" => "update-only: names the entry being edited",
			]],
			"callout" => ["create_callout", "update_callout", []],
			"module" => ["create_module", "update_module", [
				"route" => "create-only: changing a module's route is a named decline (it breaks bookmarks and links)",
				"module_id" => "update-only: names the module being edited",
			]],
			"user" => ["create_user", "update_user", [
				"user_id" => "update-only: names the user being edited",
			]],
			"template" => ["create_template", "update_template", [
				// TemplateService::FIELDS omits `routed` for the same reason: the flag
				// decides where the render file lives (templates/routed/{id}/default.php
				// vs templates/basic/{id}.php), so flipping it orphans the file.
				"routed" => "create-only: the flag decides where the template's render file is written, so it is "
					. "fixed at creation — REST's PATCH doesn't rewrite it either",
			]],
		];
	}

	/**
	 * The inverse of ai_surface_readable_by(): a difference between a create tool and
	 * its update tool must be a decision someone wrote down.
	 */
	function test_create_and_update_tools_are_symmetric_or_explained() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$unexplained = [];
		$stale = [];

		$arguments = function (string $name) use ($registry, $developer): ?array {
			$tool = $registry->get($name);

			if ($tool === null) {

				return null;
			}

			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

			return is_array($properties) ? array_keys($properties) : [];
		};

		foreach (ai_surface_create_update_pairs() as $label => [$create, $update, $reasons]) {
			$create_args = $arguments($create);
			$update_args = $arguments($update);

			T::ok($create_args !== null, "{$create} is a registered tool");
			T::ok($update_args !== null, "{$update} is a registered tool");

			if ($create_args === null || $update_args === null) {

				continue;
			}

			$asymmetric = array_merge(
				array_values(array_diff($create_args, $update_args)),
				array_values(array_diff($update_args, $create_args))
			);

			foreach ($asymmetric as $argument) {
				if (!isset($reasons[$argument])) {
					$unexplained[] = "{$label}.{$argument}";
				}
			}

			// And the reasons must not outlive the asymmetry: an argument since added
			// to the other tool is symmetric now, and its exception has become a note
			// about a decision that no longer holds.
			foreach (array_keys($reasons) as $argument) {
				if (!in_array($argument, $asymmetric, true)) {
					$stale[] = "{$label}.{$argument}";
				}
			}
		}

		T::equals(
			implode(", ", $unexplained),
			"",
			"every create/update argument difference is declared create-only or update-only with a reason"
		);
		T::equals(implode(", ", $stale), "", "no create/update exception describes an asymmetry that no longer exists");
	}

	/**
	 * Mutating tools whose validate seam must stage a staleness fingerprint, mapped
	 * to the seam itself. A create has no prior state to compare against, so only
	 * tools that address an existing record appear here.
	 *
	 * @return array<string,array{0:string,1:string}> tool => [service class, method]
	 */
	function ai_surface_fingerprinted(): array {

		return [
			"update_page" => [\BigTree\Services\PageService::class, "aiValidatePageUpdate"],
			"update_page_content" => [\BigTree\Services\PageService::class, "aiValidatePageContentUpdate"],
			"update_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryUpdate"],
			"set_module_entry_flag" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryFlag"],
			"delete_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryDelete"],
			"publish_pending_change" => [\BigTree\Services\PendingChangeService::class, "aiValidatePublishChange"],
			"reject_pending_change" => [\BigTree\Services\PendingChangeService::class, "aiValidateRejectChange"],
			"update_setting" => [\BigTree\Services\SettingService::class, "aiValidateSettingUpdate"],
			"update_user" => [\BigTree\Services\UserService::class, "aiValidateUserUpdate"],
			"update_template" => [\BigTree\Services\TemplateService::class, "aiValidateTemplateUpdate"],
			"update_callout" => [\BigTree\Services\CalloutService::class, "aiValidateCalloutUpdate"],
			"update_module" => [\BigTree\Services\ModuleService::class, "aiValidateModuleUpdate"],
			"rename_tag" => [\BigTree\Services\TagService::class, "aiValidateTagRename"],
		];
	}

	/**
	 * Mutating tools whose target the admin takes a concurrent-edit lock on, mapped
	 * to the validate seam that must name it.
	 *
	 * PageEdit, ModuleEntryEdit and SettingEdit each hold a `bigtree_locks` row for
	 * the life of the screen and show a second arrival a banner naming the holder.
	 * Every tool that writes to one of those three records has to emit the matching
	 * `lock` descriptor, or the proposal card is the one review surface that doesn't
	 * mention the person already working on it. Tags are here because they are
	 * edited from the page and entry forms' own chips.
	 *
	 * @return array<string,array{0:string,1:string}> tool => [service class, method]
	 */
	function ai_surface_locked(): array {

		return [
			"update_page" => [\BigTree\Services\PageService::class, "aiValidatePageUpdate"],
			"update_page_content" => [\BigTree\Services\PageService::class, "aiValidatePageContentUpdate"],
			"archive_page" => [\BigTree\Services\PageService::class, "aiValidatePageArchive"],
			"unarchive_page" => [\BigTree\Services\PageService::class, "aiValidatePageUnarchive"],
			"move_page" => [\BigTree\Services\PageService::class, "aiValidatePageMove"],
			"restore_page_revision" => [\BigTree\Services\PageService::class, "aiValidateRevisionRestore"],
			"update_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryUpdate"],
			"set_module_entry_flag" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryFlag"],
			"delete_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryDelete"],
			"update_setting" => [\BigTree\Services\SettingService::class, "aiValidateSettingUpdate"],
			"add_tags" => [\BigTree\Services\TagService::class, "aiValidateAddTags"],
			"remove_tags" => [\BigTree\Services\TagService::class, "aiValidateRemoveTags"],
		];
	}

	/** The source of one method, for a structural assertion about it. */
	function ai_surface_method_body(string $class, string $method): string {
		$reflection = new ReflectionMethod($class, $method);
		$file = $reflection->getFileName();

		if (!$file || !is_readable($file)) {

			return "";
		}

		$lines = file($file);

		return implode("", array_slice(
			$lines,
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));
	}

	/**
	 * The generalized read/write parity guard.
	 *
	 * Every argument of every mutating tool must be readable through some read tool,
	 * or carry an explicit exemption. This is the check that would have caught audit
	 * #4's B4 (get_page returned six of fourteen writable fields) and audit #5's C3
	 * (entry tags/OG, user company/timezone and callout groups were all write-only)
	 * as they were introduced, rather than two audits later.
	 */
	function test_every_writable_field_is_readable_or_exempt() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$map = ai_surface_readable_by();
		$read_tools = [];
		$undeclared = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() === "read") {
				$read_tools[] = $tool->name();
			}
		}

		// The search registry's tools are read tools too, and several entries above
		// name them (get_page, get_module_entry, search_tags…).
		foreach (["get_page", "get_module_entry", "search_tags", "search_users", "search_modules"] as $name) {
			$read_tools[] = $name;
		}

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() === "read") {

				continue;
			}

			$name = $tool->name();
			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

			if (!is_array($properties)) {

				continue;
			}

			T::ok(isset($map[$name]), "{$name} has a declared read surface");

			foreach (array_keys($properties) as $argument) {
				$declared = $map[$name][$argument] ?? null;

				if ($declared === null) {
					$undeclared[] = "{$name}.{$argument}";

					continue;
				}

				if (strpos($declared, "exempt:") === 0) {

					continue;
				}

				T::ok(
					in_array($declared, $read_tools, true),
					"{$name}.{$argument} is readable through {$declared}, which is a registered read tool"
				);
			}
		}

		T::equals(
			implode(", ", $undeclared),
			"",
			"every writable field names the read tool that returns it, or an explicit exemption"
		);
	}

	/** The map must not outlive the tools it describes. */
	function test_read_surface_map_has_no_stale_entries() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$names = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$names[] = $tool->name();
		}

		$stale = array_values(array_diff(array_keys(ai_surface_readable_by()), $names));

		T::equals(implode(", ", $stale), "", "no read-surface entry names a tool that no longer exists");
	}

	/**
	 * The approval re-check guard.
	 *
	 * A mutating tool that addresses an existing record must stage a staleness
	 * fingerprint, or a proposal can be approved against a record that has since
	 * been rewritten — which is exactly what audit #5's B2 found on
	 * publish_pending_change, and what A1 found on the page and entry drafts.
	 * AbstractMutatingTool hashes whatever `fingerprint` the seam returns, so the
	 * structural fact to guard is that the seam returns one at all.
	 */
	function test_every_targeted_mutation_stages_a_fingerprint() {
		$missing = [];

		foreach (ai_surface_fingerprinted() as $tool => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);

			T::ok($body !== "", "{$method}'s source was read");

			if (strpos($body, '"fingerprint" =>') === false) {
				$missing[] = "{$tool} ({$method})";
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every mutation with an existing target stages a staleness fingerprint"
		);
	}

	/**
	 * Part D: every write to a lockable record names its lock.
	 *
	 * The admin has shown a lock banner since legacy; the assistant wrote to the
	 * same three records and showed nothing, so the proposal card — the one place a
	 * change is deliberately reviewed before it lands — was silent about the
	 * colleague already editing it.
	 */
	function test_every_lockable_mutation_names_its_lock() {
		$missing = [];

		foreach (ai_surface_locked() as $tool => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);

			T::ok($body !== "", "{$method}'s source was read");

			if (strpos($body, '"lock" =>') === false) {
				$missing[] = "{$tool} ({$method})";
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every mutation on a lockable record stages its lock descriptor"
		);
	}

	/** Every locked tool is a tool the approval dispatcher still knows. */
	function test_lock_map_has_no_stale_entries() {
		$dispatched = ai_wiring_dispatch_branches();
		$stale = array_values(array_diff(array_keys(ai_surface_locked()), $dispatched));

		T::equals(implode(", ", $stale), "", "no lock entry names a tool that is no longer dispatched");
	}

	/** Every fingerprinted tool is a tool the approval dispatcher still knows. */
	function test_fingerprint_map_has_no_stale_entries() {
		$dispatched = ai_wiring_dispatch_branches();
		$stale = array_values(array_diff(array_keys(ai_surface_fingerprinted()), $dispatched));

		T::equals(implode(", ", $stale), "", "no fingerprint entry names a tool that is no longer dispatched");
	}

	/**
	 * And the mechanism itself: a fingerprint is only meaningful if the approval
	 * path actually compares it and refuses on a mismatch.
	 */
	function test_staleness_comparison_refuses_a_changed_target() {
		$descriptor = ["type" => "json_record", "store" => "templates", "id" => "zz-audit5-missing"];

		// A descriptor whose record doesn't exist hashes to "missing"; pretending it
		// hashed to something else is the same shape as a record that moved.
		$payload = [
			\BigTree\Services\AI\Tools\AbstractMutatingTool::FINGERPRINT_KEY => [
				"descriptor" => $descriptor,
				"hash" => "not-the-current-hash",
			],
		];

		$stale = AIChatService::stalenessError($payload);

		T::ok(is_array($stale), "a mismatched fingerprint produces a refusal");
		T::equals((string)($stale["mode"] ?? ""), "error", "the refusal is a mode=error result");
		T::ok(
			strpos((string)$stale["message"], "changed since it was proposed") !== false,
			"and says why, so the model can ask again"
		);

		// A matching one passes.
		$payload[\BigTree\Services\AI\Tools\AbstractMutatingTool::FINGERPRINT_KEY]["hash"] =
			\BigTree\Services\AI\ProposalFingerprint::compute($descriptor);

		T::equals(AIChatService::stalenessError($payload), null, "a matching fingerprint passes through");
	}

	/**
	 * A failed approval must be a distinct, non-success, still-actionable state —
	 * the three properties audit #5's B1 found missing at once.
	 */
	function test_failed_is_a_distinct_actionable_status() {
		T::ok(ProposalStore::FAILED !== ProposalStore::APPROVED, "failed is not approved");
		T::ok(in_array(ProposalStore::PENDING, ProposalStore::ACTIONABLE, true), "pending is actionable");
		T::ok(in_array(ProposalStore::FAILED, ProposalStore::ACTIONABLE, true), "failed is actionable (retryable)");
		T::ok(!in_array(ProposalStore::APPROVED, ProposalStore::ACTIONABLE, true), "approved is terminal");
		T::ok(!in_array(ProposalStore::REJECTED, ProposalStore::ACTIONABLE, true), "rejected is terminal");
		T::ok(!in_array(ProposalStore::EXPIRED, ProposalStore::ACTIONABLE, true), "expired is terminal");
	}
