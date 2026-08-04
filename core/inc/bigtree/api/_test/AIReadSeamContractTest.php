<?php
	/**
	 * Audit #16: the read seams, and the one that isn't a tool.
	 *
	 * Every read-permission guard in this suite is keyed to a *tool's backend
	 * method*. `EmbeddingService::search` is not one — `semantic_search` calls
	 * `SearchService::semanticSearch`, whose entire body is
	 * `return EmbeddingService::search(...)`. So the vector index answered the same
	 * question as the keyword path ("which module entries match this?") while
	 * applying a weaker filter, and nothing in the suite was looking at it: it
	 * gated on module-wide `v` (the *best* of any group grant) where the keyword
	 * path gates per row, and it never filtered archived entries at all.
	 *
	 * These are the read-side analogue of AISurfaceGuardTest's fingerprint map: a
	 * seam is enumerated by hand, and adding a new one without deciding how it
	 * filters fails here rather than leaking a row two audits later.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\EmbeddingService;
	use BigTree\Services\SearchService;

	/**
	 * E1 — every seam that returns module-entry rows to the model, mapped to the
	 * per-row permission call it makes, or an explicit exemption with its reason.
	 *
	 * Module-level access ("may this user open the module at all") is the wrong
	 * question for a row on a group-based module: `userModuleLevel` returns the
	 * best of any group grant, so an editor scoped to one group passes it for every
	 * other group's rows. The right question is `userRowLevel`/`userEntryLevel`
	 * against the row itself.
	 *
	 * @return array<string,array{0:string,1:string,2:string}> seam => [class, method, required call | "exempt: reason"]
	 */
	function ai_read_seam_row_filters(): array {

		return [
			"search_module_entries" => [SearchService::class, "searchModuleEntries", "userRowLevel"],
			"get_module_entry" => [SearchService::class, "getModuleEntryDetail", "userEntryLevel"],
			"list_module_entries" => [AutoModuleService::class, "aiListEntries", "userRowLevel"],
			// Audit #16 A1. Reached through a one-line passthrough
			// (SearchService::semanticSearch) rather than being a backend method
			// itself, which is exactly why no existing guard covered it.
			"semantic_search" => [EmbeddingService::class, "search", "userRowLevel"],
			// The legitimate exemption, verified against REST this pass: this mirrors
			// `GET /modules/{id}/forms/{sid}/relation-options`, whose permission is the
			// *owning* module's `v` — the rows it returns are the rows the admin's own
			// relation picker offers, and filtering them per row here would give the
			// assistant a smaller picker than the screen it is standing in for.
			"get_relation_options" => [
				AutoModuleService::class,
				"aiRelationOptions",
				"exempt: mirrors the admin's own relation picker, gated by the owning module (parity with REST)",
			],
		];
	}

	function test_every_module_entry_read_seam_filters_per_row_or_is_exempt() {
		$seams = ai_read_seam_row_filters();
		$missing = [];

		T::ok(count($seams) > 0, "read seams were enumerated");

		foreach ($seams as $seam => [$class, $method, $expected]) {
			$body = ai_surface_method_body($class, $method);

			T::ok($body !== "", "{$method}'s source was read");

			if (strpos($expected, "exempt:") === 0) {
				continue;
			}

			if (strpos($body, "PermissionService::" . $expected) === false) {
				$missing[] = "{$seam} ({$method} never calls {$expected})";
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every seam returning module-entry rows applies a per-row permission check"
		);
	}

	/**
	 * An exemption is a written decision, not a blank. Anything exempt has to say
	 * why, at enough length to be an argument rather than a shrug.
	 */
	function test_read_seam_exemptions_carry_a_reason() {
		$unexplained = [];

		foreach (ai_read_seam_row_filters() as $seam => [, , $expected]) {
			if (strpos($expected, "exempt:") !== 0) {
				continue;
			}

			if (strlen(trim(substr($expected, strlen("exempt:")))) < 30) {
				$unexplained[] = $seam;
			}
		}

		T::equals(implode(", ", $unexplained), "", "every exempt read seam states its reason");
	}

	/** Every seam named above still exists as a real method. */
	function test_read_seam_map_has_no_stale_entries() {
		$stale = [];

		foreach (ai_read_seam_row_filters() as $seam => [$class, $method, ]) {
			if (!method_exists($class, $method)) {
				$stale[] = "{$seam} ({$class}::{$method})";
			}
		}

		T::equals(implode(", ", $stale), "", "no read-seam entry names a method that no longer exists");
	}

	/**
	 * E3 — the archived-content contract on the vector index.
	 *
	 * `indexPage` has refused to index an archived page since the index shipped,
	 * and `search()` re-checks the flag on the page hit as belt and braces.
	 * `indexModuleEntry` had neither, and nothing dropped an entry's vector when it
	 * was archived — so an archived entry stayed semantically searchable forever
	 * and was handed to the model as live content. What one branch refuses, the
	 * other must refuse too.
	 */
	function test_the_vector_index_refuses_archived_content_on_both_branches() {
		$index_page = ai_surface_method_body(EmbeddingService::class, "indexPage");
		$index_entry = ai_surface_method_body(EmbeddingService::class, "indexModuleEntry");

		T::ok($index_page !== "", "indexPage's source was read");
		T::ok($index_entry !== "", "indexModuleEntry's source was read");

		T::ok(
			strpos($index_page, 'Flag::isOn($page["archived"]') !== false,
			"indexPage refuses to index an archived page (the positive control)"
		);

		$missing = [];

		if (strpos($index_entry, 'Flag::isOn($row["archived"]') === false) {
			$missing[] = "indexModuleEntry does not refuse an archived entry";
		}

		if (strpos($index_entry, "self::deleteModuleEntry(") === false) {
			$missing[] = "indexModuleEntry does not drop the vector of a row it will not index";
		}

		T::equals(
			implode(", ", $missing),
			"",
			"indexModuleEntry refuses archived content the way indexPage does"
		);
	}

	function test_the_vector_search_filters_archived_on_both_branches() {
		$search = ai_surface_method_body(EmbeddingService::class, "search");

		T::ok($search !== "", "search's source was read");
		T::ok(
			strpos($search, 'Flag::isOn($page["archived"]') !== false,
			"search filters archived pages (the positive control)"
		);
		T::ok(
			strpos($search, 'Flag::isOn($entry_row["archived"]') !== false,
			"search filters archived module entries"
		);
	}

	/**
	 * The index converges on its own. Both flag paths write the `archived` column
	 * with a raw SQL::update, which bypasses BigTreeAutoModule::updateItem and so
	 * the embedding hook that hangs off it — so unless each one re-indexes, an
	 * archived entry keeps its vector until somebody happens to re-save the row.
	 * The two paths are parallel today and must stay so: REST's toggle and the
	 * assistant's approved flag change are the same write.
	 */
	function test_both_archive_flag_paths_reconverge_the_vector_index() {
		$toggle = ai_surface_method_body(AutoModuleService::class, "toggleFlag");
		$ai_flag = ai_surface_method_body(AutoModuleService::class, "aiSetEntryFlag");

		T::ok($toggle !== "", "toggleFlag's source was read");
		T::ok($ai_flag !== "", "aiSetEntryFlag's source was read");

		// Collected rather than asserted one at a time: a T::ok failure throws, and
		// the point of this leg is that the two paths are reported *together* — one
		// of them drifting is the finding.
		$missing = [];

		foreach (["REST's flag toggle" => $toggle, "the assistant's approved flag change" => $ai_flag] as $label => $body) {
			if (strpos($body, "reindexAfterFlag(") === false) {
				$missing[] = $label;
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"both archive-flag paths reconverge the vector index"
		);
	}
