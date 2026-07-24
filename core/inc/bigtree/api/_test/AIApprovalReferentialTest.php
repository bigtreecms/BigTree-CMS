<?php
	/**
	 * Audit #8 guard E1: the referenced-entity boundary at approval.
	 *
	 * Audits 1–7 hardened the *value* boundary — a staged value is never trusted on
	 * the way back out, and a targeted mutation fingerprints its row so a stale edit
	 * is refused. `AISurfaceGuardTest::test_every_targeted_mutation_stages_a_fingerprint`
	 * enforces that leg, but it is structurally blind to creates: a create has no
	 * existing target row, so it never appears in that map — and with it, nothing
	 * mechanically required the *container* a create/move files into (a page's parent,
	 * a module's group, a move's destination, a redirect's site) to still be valid at
	 * approval. That gap is exactly what audit #8's A1–A3 found by reading.
	 *
	 * This is the create-side analogue of the fingerprint leg. For every create/move
	 * tool it names the container reference the tool resolves at staging and the
	 * approval seam that must re-read it, and asserts the seam's source contains that
	 * re-read. A create/move tool with a genuine external reference must be mapped;
	 * one with none must be listed as exempt with a reason. Adding a create/move tool
	 * without deciding which fails the enumeration leg — the whole point.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\FourOhFourService;
	use BigTree\Services\ModuleService;
	use BigTree\Services\PageService;

	/**
	 * Every create/move tool that files a record under a container it doesn't own,
	 * mapped to the approval seam and the re-read that seam must perform.
	 *
	 * `contains` is the substring(s) whose presence in the approval method's source
	 * proves the referenced record is re-read at approval — existence at minimum, and
	 * archived-state where the container can be archived (pages).
	 *
	 * @return array<string,array{0:string,1:string,2:list<string>}> tool => [class, method, contains]
	 */
	function ai_approval_referential(): array {

		return [
			// A1: the parent page — re-read for existence *and* archived, so a page can
			// never be created live inside a branch archived since the proposal staged.
			"create_page" => [PageService::class, "aiCreatePage", ["aiParentUsableError"]],
			// A3: the destination parent — existence was re-checked already, archived is
			// the leg A3 added (both spelled by the shared helper).
			"move_page" => [PageService::class, "aiMovePage", ['SQL::exists("bigtree_pages"', "aiParentUsableError"]],
			// A2: the module group — re-resolved and degraded ungrouped-with-note if it
			// vanished, mirroring create_callout.
			"create_module" => [ModuleService::class, "aiCreateModule", ['BigTreeJSONDB::exists("module-groups"']],
			// The owning module (and its form/table) — re-read before the entry write.
			"create_module_entry" => [
				AutoModuleService::class, "aiCreateEntry",
				['BigTreeJSONDB::get("modules"', "aiResolveModuleForm"],
			],
			// The multi-site key — re-resolved so a redirect can't be filed under a site
			// removed from config since staging.
			"create_redirect" => [FourOhFourService::class, "aiCreateRedirect", ["aiResolveSiteKey"]],
			// The positive control the plan generalized: the callout group is re-resolved
			// at approval and the callout degrades ungrouped-with-note if it's gone.
			"create_callout" => [CalloutService::class, "aiCreateCallout", ["aiAddCalloutToGroup"]],
			// The mirror image, once a group could be created with members: each callout
			// is re-read at approval (a deleted one is dropped with a note rather than
			// written into the group's list as a member that renders as nothing).
			"create_callout_group" => [
				CalloutService::class, "aiCreateCalloutGroup",
				['BigTreeJSONDB::exists("callouts"', "aiRemoveCalloutFromGroups"],
			],
		];
	}

	/**
	 * Create/move tools that reference no container they don't own, so there is
	 * nothing external to re-validate at approval. Each carries the reason, the same
	 * discipline the read-surface map uses: the decision is recorded, not skipped.
	 *
	 * @return array<string,string>
	 */
	function ai_approval_referential_exempt(): array {

		return [
			"create_template" => "self-contained: writes one JSON-DB record; its id is re-checked free at approval",
			"create_module_group" => "self-contained: only a name, re-checked for uniqueness and length at approval",
			"create_user" => "no container; email uniqueness/format is re-checked at approval",
		];
	}

	/**
	 * Every create/move tool's approval seam re-reads the container it files into.
	 *
	 * This is the leg that would have failed on A1 (create_page never re-fetched its
	 * parent), A2 (create_module trusted the staged group id) and A3 (move_page never
	 * checked the destination's archived state).
	 */
	function test_every_container_reference_is_revalidated_at_approval() {
		$missing = [];

		foreach (ai_approval_referential() as $tool => [$class, $method, $contains]) {
			$body = ai_surface_method_body($class, $method);

			T::ok($body !== "", "{$method}'s source was read");

			foreach ($contains as $needle) {
				if (strpos($body, $needle) === false) {
					$missing[] = "{$tool} ({$method}) is missing “{$needle}”";
				}
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every create/move tool re-reads its container reference at approval"
		);
	}

	/**
	 * The enumeration leg: no create/move tool escapes the decision.
	 *
	 * Every mutating tool named create_* or move_* must either be in the referential
	 * map (it re-validates a container) or in the exemption list (it references none).
	 * A new one that is neither fails here — the split A1–A3 closed can't silently
	 * reopen the next time a create tool lands.
	 */
	function test_every_create_or_move_tool_is_classified() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$mapped = ai_approval_referential();
		$exempt = ai_approval_referential_exempt();
		$unclassified = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() === "read") {

				continue;
			}

			$name = $tool->name();

			if (!preg_match('/^(create|move)_/', $name)) {

				continue;
			}

			if (!isset($mapped[$name]) && !isset($exempt[$name])) {
				$unclassified[] = $name;
			}
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every create/move tool is either referential-mapped or explicitly exempt"
		);
	}

	/** Neither map may outlive the tools it describes. */
	function test_referential_maps_have_no_stale_entries() {
		$dispatched = ai_wiring_dispatch_branches();
		$stale = array_values(array_diff(
			array_merge(array_keys(ai_approval_referential()), array_keys(ai_approval_referential_exempt())),
			$dispatched
		));

		T::equals(implode(", ", $stale), "", "no referential entry names a tool that is no longer dispatched");
	}
