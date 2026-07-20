<?php
	/**
	 * The registry and the approval dispatcher must agree.
	 *
	 * A two-phase tool is wired in two places: buildRegistry() offers it during a
	 * turn, and executeProposal() performs the write once the user approves. Adding a
	 * tool and forgetting the dispatch branch is silent — the proposal stages, the
	 * user approves it, and nothing happens. These guard that seam for the whole
	 * catalog rather than tool by tool.
	 */

	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\ProposalStore;

	/** The real registry AIChatService builds for a chat turn. */
	function ai_wiring_registry() {
		static $registry = null;

		if ($registry === null) {
			$service = new AIChatService();
			$build = new ReflectionMethod($service, "buildRegistry");
			$build->setAccessible(true);
			$registry = $build->invoke($service, new ProposalStore());
		}

		return $registry;
	}

	function ai_wiring_user(int $level) {

		return (object)["id" => 1, "level" => $level, "permissions" => []];
	}

	/**
	 * Tool names a given AIChatService method has a `case` branch for. Scoped to the
	 * method's own line range so executeProposal's branches and auditDescriptor's
	 * branches can be compared against each other rather than blurred together.
	 *
	 * @return list<string>
	 */
	function ai_wiring_cases_in(string $method): array {
		static $lines = null;

		if ($lines === null) {
			$lines = file(SERVER_ROOT . "core/inc/bigtree/services/AIChatService.php");
		}

		$reflection = new ReflectionMethod(AIChatService::class, $method);
		$body = implode("", array_slice(
			$lines,
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));

		preg_match_all('/case "([a-z_]+)":/', $body, $matches);

		return array_values(array_unique($matches[1]));
	}

	/** Tool names the approval dispatcher has a branch for. */
	function ai_wiring_dispatch_branches(): array {

		return ai_wiring_cases_in("executeProposal");
	}

	/**
	 * Tools that execute after approval but deliberately write no audit row. Keep
	 * this list short and justified — anything here is invisible in the audit UI's
	 * "AI" filter.
	 *
	 * @return list<string>
	 */
	function ai_wiring_audit_skips(): array {

		return [];
	}

	function test_ai_registry_builds_for_every_level() {
		$registry = ai_wiring_registry();
		$counts = [];

		foreach ([0, 1, 2] as $level) {
			$definitions = $registry->definitions(ai_wiring_user($level));
			T::ok(count($definitions) > 0, "registry builds for level {$level}");
			$counts[$level] = count($definitions);

			foreach ($definitions as $definition) {
				$name = (string)($definition["function"]["name"] ?? "");
				T::ok($name !== "", "every offered tool has a name");

				$parameters = $definition["function"]["parameters"] ?? [];
				$stray = array_diff(array_keys(is_array($parameters) ? $parameters : []), ["type", "properties", "required"]);
				T::equals($stray, [], "{$name}'s parameter schema has no stray top-level keys");
			}
		}

		// Higher levels are strictly additive — a developer never loses a tool an
		// editor has.
		T::ok($counts[1] >= $counts[0], "admins see at least what editors see");
		T::ok($counts[2] >= $counts[1], "developers see at least what admins see");
	}

	function test_every_mutating_tool_has_an_approval_dispatch_branch() {
		$registry = ai_wiring_registry();
		$dispatched = ai_wiring_dispatch_branches();
		$missing = [];

		foreach ($registry->availableTools(ai_wiring_user(2)) as $tool) {
			// Read tools return their result inline and never stage a proposal, so
			// they have nothing to dispatch on approval.
			if ($tool->kind() === "read") {
				continue;
			}

			if (!in_array($tool->name(), $dispatched, true)) {
				$missing[] = $tool->name();
			}
		}

		T::equals($missing, [], "every mutating tool can be executed after approval");
	}

	function test_every_dispatch_branch_matches_a_real_tool() {
		$registry = ai_wiring_registry();
		$names = [];

		foreach ($registry->definitions(ai_wiring_user(2)) as $definition) {
			$names[] = (string)$definition["function"]["name"];
		}

		$orphaned = [];

		foreach (ai_wiring_dispatch_branches() as $branch) {
			if (!in_array($branch, $names, true)) {
				$orphaned[] = $branch;
			}
		}

		T::equals($orphaned, [], "no dispatch branch refers to a tool that no longer exists");
	}

	/**
	 * The third leg of the seam. A tool can be registered and dispatched and still
	 * leave no trace: auditDescriptor's `default` returns null and recordApprovalAudit
	 * silently skips. That is how the whole Phase-4 catalog shipped unaudited.
	 */
	function test_every_dispatch_branch_has_an_audit_descriptor() {
		$audited = ai_wiring_cases_in("auditDescriptor");
		$skips = ai_wiring_audit_skips();
		$missing = [];

		// The comparison is source-scraped, so an empty side would pass vacuously.
		T::ok(count($audited) > 0, "auditDescriptor's branches were parsed");
		T::ok(count(ai_wiring_dispatch_branches()) > 0, "executeProposal's branches were parsed");

		foreach (ai_wiring_dispatch_branches() as $branch) {
			if (!in_array($branch, $audited, true) && !in_array($branch, $skips, true)) {
				$missing[] = $branch;
			}
		}

		T::equals($missing, [], "every approved tool writes an audit row (or is on the documented skip list)");
	}

	function test_audit_descriptors_do_not_outlive_their_tools() {
		$dispatched = ai_wiring_dispatch_branches();
		$orphaned = [];

		foreach (ai_wiring_cases_in("auditDescriptor") as $branch) {
			if (!in_array($branch, $dispatched, true)) {
				$orphaned[] = $branch;
			}
		}

		T::equals($orphaned, [], "no audit descriptor refers to a tool that is no longer dispatched");
	}

	function test_audit_skip_list_only_names_real_tools() {
		$dispatched = ai_wiring_dispatch_branches();

		foreach (ai_wiring_audit_skips() as $skip) {
			T::ok(in_array($skip, $dispatched, true), "audit skip “{$skip}” names a tool that still exists");
		}
	}

	function test_new_catalog_tools_are_registered() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$names = [];

		foreach ($registry->definitions($developer) as $definition) {
			$names[] = (string)$definition["function"]["name"];
		}

		foreach ([
			"update_page_content", "unarchive_page", "move_page",
			"set_module_entry_flag", "delete_module_entry",
			"remove_tags", "reject_pending_change",
			"get_module", "get_module_schema", "get_pending_change",
			// Audit #2 phase 4.
			"update_callout", "update_module", "get_callout",
			"get_audit_trail", "get_page_revisions", "restore_page_revision",
		] as $expected) {
			T::ok(in_array($expected, $names, true), "{$expected} is registered");
		}
	}

	function test_tagging_and_review_tools_reach_editors() {
		$registry = ai_wiring_registry();
		$names = [];

		foreach ($registry->definitions(ai_wiring_user(0)) as $definition) {
			$names[] = (string)$definition["function"]["name"];
		}

		// Editors tag their own content and withdraw their own drafts; the backend
		// enforces the narrower rules (existing tags only, own changes only).
		foreach (["add_tags", "remove_tags", "reject_pending_change", "update_page_content"] as $expected) {
			T::ok(in_array($expected, $names, true), "{$expected} is offered to editors");
		}

		// Still hidden: the admin/developer surfaces.
		foreach (["update_setting", "create_user", "create_template", "create_module"] as $hidden) {
			T::ok(!in_array($hidden, $names, true), "{$hidden} stays hidden from editors");
		}
	}
