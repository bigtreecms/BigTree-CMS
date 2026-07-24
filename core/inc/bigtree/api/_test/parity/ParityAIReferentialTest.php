<?php
	/**
	 * Audit #8 Phase 1 (A1–A3): the referenced-entity boundary at approval.
	 *
	 * A proposal sits in the store for up to 24h between staging and approval. The
	 * staging seam validates the container a new record files into — a page's parent,
	 * a module's group, a move's destination — but some approval seams re-asked that
	 * question and some didn't. These stage a proposal, mutate the container out from
	 * under it, and assert approval refuses (or, where grouping is cosmetic, degrades
	 * with a disclosed note) rather than writing a broken record.
	 */

	use BigTree\Services\ModuleService;
	use BigTree\Services\PageService;

	/** A live top-level page to use as a create/move parent, owned by nobody. */
	function parity_a8_parent(PageService $svc, $dev): int {
		$validated = $svc->aiValidatePageCreate([
			"parent" => 0,
			"nav_title" => "AI Audit8 Parent " . bin2hex(random_bytes(3)),
			"template" => "",
			"external" => "https://example.com/audit8-" . bin2hex(random_bytes(3)),
		], $dev);

		if (empty($validated["ok"])) {

			return 0;
		}

		return (int)($svc->aiCreatePage($validated["payload"], $dev)["page_id"] ?? 0);
	}

	/**
	 * A1: create_page must re-check its parent at approval — an archived parent then
	 * refuses, so no page is ever created live inside a branch archived since staging.
	 */
	function test_parity_ai_create_page_refuses_a_parent_archived_after_staging() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$parent_id = 0;

		try {
			$parent_id = parity_a8_parent($svc, $dev);
			T::ok($parent_id > 0, "parent page created");

			// Stage a create under the live parent — it validates cleanly.
			$staged = $svc->aiValidatePageCreate([
				"parent" => $parent_id,
				"nav_title" => "ZZ Audit8 Child " . bin2hex(random_bytes(3)),
				"external" => "https://example.com/audit8-child",
			], $dev);
			T::ok(!empty($staged["ok"]), "the create validates against the live parent");

			// Archive the parent *after* the proposal was staged.
			$archive = $svc->aiValidatePageArchive(["id" => $parent_id], $dev);
			$svc->aiArchivePage($archive["payload"], $dev);

			// Approving now must refuse rather than write a live page in a hidden branch.
			$result = $svc->aiCreatePage($staged["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "error", "approval refuses an archived parent");
			T::ok(
				strpos((string)($result["message"] ?? ""), "archived") !== false,
				"and says the parent is archived"
			);

			// Nothing was written under the archived parent.
			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages WHERE parent = ?", $parent_id),
				0,
				"no child page was created"
			);
		} finally {
			parity_delete_page($parent_id);
			parity_delete_users($dev_id);
		}
	}

	/** A1: create_page must refuse a parent deleted after staging rather than orphan the page. */
	function test_parity_ai_create_page_refuses_a_parent_deleted_after_staging() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$parent_id = 0;

		try {
			$parent_id = parity_a8_parent($svc, $dev);
			T::ok($parent_id > 0, "parent page created");

			$staged = $svc->aiValidatePageCreate([
				"parent" => $parent_id,
				"nav_title" => "ZZ Audit8 Orphan " . bin2hex(random_bytes(3)),
				"external" => "https://example.com/audit8-orphan",
			], $dev);
			T::ok(!empty($staged["ok"]), "the create validates against the live parent");

			// Delete the parent out from under the proposal.
			SQL::delete("bigtree_pages", $parent_id);
			$deleted_parent = $parent_id;
			$parent_id = 0;

			$result = $svc->aiCreatePage($staged["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "error", "approval refuses a deleted parent");
			T::ok(
				strpos((string)($result["message"] ?? ""), "does not exist") !== false,
				"and says the parent is gone"
			);

			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages WHERE parent = ?", $deleted_parent),
				0,
				"no orphan page was created"
			);
		} finally {
			parity_delete_page($parent_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A3: move_page must refuse a destination archived after staging, at both the
	 * staging seam and — if it slips through — the approval seam.
	 */
	function test_parity_ai_move_page_refuses_an_archived_destination() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$mover_id = 0;
		$destination_id = 0;

		try {
			$mover_id = parity_a8_parent($svc, $dev);
			$destination_id = parity_a8_parent($svc, $dev);
			T::ok($mover_id > 0 && $destination_id > 0, "page-to-move and destination created");

			// Stage the move while the destination is live.
			$staged = $svc->aiValidatePageMove(["id" => $mover_id, "parent" => $destination_id], $dev);
			T::ok(!empty($staged["ok"]), "the move validates against the live destination");

			// Archive the destination.
			$archive = $svc->aiValidatePageArchive(["id" => $destination_id], $dev);
			$svc->aiArchivePage($archive["payload"], $dev);

			// Staging a fresh move now refuses outright.
			$restaged = $svc->aiValidatePageMove(["id" => $mover_id, "parent" => $destination_id], $dev);
			T::ok(isset($restaged["error"]), "staging refuses a move into an archived branch");
			T::ok(
				strpos((string)$restaged["error"], "archived") !== false,
				"and says the destination is archived"
			);

			// And approving the already-staged move refuses too.
			$result = $svc->aiMovePage($staged["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "error", "approval refuses the archived destination");

			// The page never moved.
			T::equals(
				(int)SQL::fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $mover_id),
				0,
				"the page stayed at the site root"
			);
		} finally {
			parity_delete_page($mover_id);
			parity_delete_page($destination_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A2: create_module must re-resolve its group at approval, degrading to
	 * ungrouped-with-note when the group was deleted after staging — the module is
	 * still created (grouping is cosmetic), never filed under a dead group id.
	 */
	function test_parity_ai_create_module_degrades_when_group_deleted_after_staging() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new ModuleService();
		$dev = (object)["id" => parity_seed_user(["level" => 2]), "level" => 2, "permissions" => []];
		$dev_id = (int)$dev->id;
		$group_id = "";
		$module_id = "";

		try {
			$group_name = "zz Audit8 Group " . bin2hex(random_bytes(3));
			$group = $svc->aiCreateModuleGroup(["name" => $group_name], $dev);
			$group_id = (string)($group["id"] ?? "");
			T::ok($group_id !== "", "module group created");

			// Stage a module create filed into that group.
			$staged = $svc->aiValidateModuleCreate([
				"name" => "zz Audit8 Module " . bin2hex(random_bytes(3)),
				"group" => $group_id,
			], $dev);
			T::ok(!empty($staged["ok"]), "the module create validates against the live group");
			T::equals((string)$staged["payload"]["group"], $group_id, "and stages the resolved group id");

			// Delete the group out from under the proposal.
			BigTreeJSONDB::delete("module-groups", $group_id);
			$group_id = "";

			$result = $svc->aiCreateModule($staged["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "created", "the module is still created");
			$module_id = (string)($result["id"] ?? "");
			T::equals((string)($result["group"] ?? "x"), "", "but filed ungrouped");
			T::ok(
				strpos((string)($result["note"] ?? ""), "no longer exists") !== false,
				"and the note discloses the group vanished"
			);

			// The stored record carries no dead group id.
			$stored = BigTreeJSONDB::get("modules", $module_id);
			T::ok(empty($stored["group"]), "the stored module has no group");
		} finally {
			if ($module_id !== "" && BigTreeJSONDB::exists("modules", $module_id)) {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			if ($group_id !== "" && BigTreeJSONDB::exists("module-groups", $group_id)) {
				BigTreeJSONDB::delete("module-groups", $group_id);
			}

			parity_delete_users($dev_id);
		}
	}
