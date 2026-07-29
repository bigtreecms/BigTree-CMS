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
			// Audit #12 A3: the same module group, on the tool the name-keyed
			// enumeration below never asked. It files a module under a group exactly as
			// create_module does — and was written four audits *after* that defect was
			// found and fixed on create_module, because "scaffold_module" is neither a
			// create_* nor a move_*.
			"scaffold_module" => [ModuleService::class, "aiScaffoldModule", ['BigTreeJSONDB::exists("module-groups"']],
		];
	}

	/**
	 * Mutating tools that act on a record that already exists, so there is no
	 * container to re-read — the third answer, alongside "re-validates one" and
	 * "references none".
	 *
	 * Most targeted mutations answer this by appearing in
	 * `AISurfaceGuardTest::ai_surface_fingerprinted()`, which is a stronger statement:
	 * they stamp the target's fingerprint at staging and refuse a stale edit. These
	 * are the ones that don't fingerprint — each names why the act itself carries no
	 * new reference.
	 *
	 * @return array<string,string>
	 */
	function ai_approval_referential_targeted(): array {

		return [
			"archive_page" => "flips a flag on a page it names; the page itself is re-read at approval",
			"unarchive_page" => "flips a flag on a page it names; the page itself is re-read at approval",
			"save_page_revision" => "snapshots the page it names — the snapshot references nothing else",
			"restore_page_revision" => "replays a stored snapshot onto the page it came from",
			"add_tags" => "attaches tag names to a page or entry it names; a missing tag is coined, not filed",
			"remove_tags" => "detaches tag names from a page or entry it names",
			"merge_tags" => "rewrites tag rows in place; both sides are re-read at approval",
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
	 * The enumeration leg: no tool that files a new record escapes the decision.
	 *
	 * This used to enumerate *by name* — "every mutating tool called create_* or
	 * move_*" — which is a guess about what a tool does dressed as a rule about what
	 * it is called. `scaffold_module` is named neither, so the one tool in the
	 * catalogue with the least reversible write was the one tool the referential
	 * contract never asked about, and audit #12 A3 found it filing a module under an
	 * unresolved, never-re-checked group id: exactly the defect audit #8 A2 had
	 * already found and fixed on `create_module`.
	 *
	 * So it enumerates by behaviour instead, and from the other end. Every mutating
	 * tool is one of four things, and it must say which:
	 *
	 *   - referential-mapped — it files into a container it re-reads at approval;
	 *   - exempt — it creates something that references no container;
	 *   - fingerprinted — it targets an existing row, and stamps that row's
	 *     fingerprint (AISurfaceGuardTest's own map, reused rather than restated);
	 *   - targeted — it acts on an existing record without fingerprinting, with the
	 *     reason written down.
	 *
	 * A new tool of any shape fails here until someone decides which.
	 */
	function test_every_mutating_tool_is_referentially_classified() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$mapped = ai_approval_referential();
		$exempt = ai_approval_referential_exempt();
		$targeted = ai_approval_referential_targeted();
		$fingerprinted = ai_surface_fingerprinted();
		$unclassified = [];
		$double = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() === "read") {

				continue;
			}

			$name = $tool->name();
			$hits = (int)isset($mapped[$name]) + (int)isset($exempt[$name])
				+ (int)isset($targeted[$name]) + (int)isset($fingerprinted[$name]);

			if ($hits === 0) {
				$unclassified[] = $name;
			} elseif ($hits > 1) {
				$double[] = $name;
			}
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every mutating tool re-validates a container, references none, or targets an existing record"
		);
		T::equals(
			implode(", ", $double),
			"",
			"and no tool is classified two ways at once"
		);
	}

	/** None of the three maps may outlive the tools it describes. */
	function test_referential_maps_have_no_stale_entries() {
		$dispatched = ai_wiring_dispatch_branches();
		$stale = array_values(array_diff(
			array_merge(
				array_keys(ai_approval_referential()),
				array_keys(ai_approval_referential_exempt()),
				array_keys(ai_approval_referential_targeted())
			),
			$dispatched
		));

		T::equals(implode(", ", $stale), "", "no referential entry names a tool that is no longer dispatched");
	}
