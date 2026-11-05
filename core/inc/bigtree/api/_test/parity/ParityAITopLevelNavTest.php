<?php
	/**
	 * Legacy's top-level `in_nav` clamp, on all three of its legs.
	 *
	 * Legacy pins the flag for anyone below developer whenever the page is at top
	 * level, on three write paths: `updatePage` (admin.php:8830), `submitPageChange`
	 * (:8022) and the move (admin.php:9044). The move leg was ported properly —
	 * aiMovePage clears the flag and the card discloses it. The other two were never
	 * ported at all, so "put About in the main menu" succeeded for a level-0 editor
	 * with `e` access, on a card that said nothing about a site-wide change; and the
	 * reverse, taking a top-level page *out* of the nav, was equally reachable.
	 *
	 * It is not an "AI is looser than REST" finding — `PATCH /pages/{id}` had the
	 * identical hole, which is why every parity guard was correctly silent. So the two
	 * surfaces are asserted against the same rule and against their different ways of
	 * saying so: REST keeps legacy's silent pin (drop the key, write the rest), the
	 * assistant refuses and says why, and the move path keeps its disclosure — the one
	 * place the nav change is a consequence of what was asked rather than the thing
	 * being asked for.
	 *
	 * The threshold is legacy's `< 2`, so the administrator legs below are not
	 * redundant with the editor ones: they are the half of the rule that is easiest to
	 * port wrong.
	 */

	use BigTree\Services\PageService;

	/** A level-0 editor with edit access to the whole tree. */
	function parity_nav_editor(): array {
		$id = parity_seed_user(["level" => 0, "permissions" => ["page" => [0 => "e"]]]);

		return [$id, (object)["id" => $id, "level" => 0, "permissions" => ["page" => [0 => "e"]]]];
	}

	/** A level-1 administrator who publishes — legacy clamps this actor too. */
	function parity_nav_administrator(): array {
		$id = parity_seed_user(["level" => 1, "permissions" => ["page" => [0 => "p"]]]);

		return [$id, (object)["id" => $id, "level" => 1, "permissions" => ["page" => [0 => "p"]]]];
	}

	/** A developer, who owns the top-level navigation. */
	function parity_nav_developer(): array {
		$id = parity_seed_user(["level" => 2]);

		return [$id, (object)["id" => $id, "level" => 2, "permissions" => []]];
	}

	function parity_nav_flag(int $page_id): string {

		return (string)SQL::fetchSingle("SELECT in_nav FROM bigtree_pages WHERE id = ?", $page_id);
	}

	/** The AI seam refuses, for both actors below developer and in both directions. */
	function test_parity_the_assistant_refuses_a_top_level_nav_change() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		$hidden_id = 0;
		$shown_id = 0;

		try {
			$hidden_id = parity_seed_page(["parent" => 0, "in_nav" => "", "nav_title" => "ZZ Nav Hidden"]);
			$shown_id = parity_seed_page(["parent" => 0, "in_nav" => "on", "nav_title" => "ZZ Nav Shown"]);

			foreach ([parity_nav_editor(), parity_nav_administrator()] as [, $actor]) {
				$level = (int)$actor->level;

				$adding = $svc->aiValidatePageUpdate([
					"id" => (string)$hidden_id,
					"in_nav" => true,
				], $actor);

				T::ok(
					isset($adding["denied"]),
					"a level-{$level} actor can't add a top-level page to the navigation"
				);
				T::ok(
					strpos((string)($adding["denied"] ?? ""), "level {$level}") !== false
						&& strpos((string)($adding["denied"] ?? ""), "level 2") !== false,
					"and the refusal names both levels, so the model can say who to ask"
				);

				$removing = $svc->aiValidatePageUpdate([
					"id" => (string)$shown_id,
					"in_nav" => false,
				], $actor);

				T::ok(
					isset($removing["denied"]),
					"nor take one out of it — legacy pins the flag in both directions"
				);
			}
		} finally {
			parity_delete_page($hidden_id);
			parity_delete_page($shown_id);
			parity_cleanup_tracked();
		}
	}

	/** The approval seam re-asks, like every other level rule. */
	function test_parity_the_approval_seam_re_asks_the_top_level_nav_rule() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[, $administrator] = parity_nav_administrator();
		$page_id = 0;

		try {
			$page_id = parity_seed_page(["parent" => 0, "in_nav" => "", "nav_title" => "ZZ Nav Approval"]);

			// A payload staged before the rule (or by a developer) is never trusted on
			// the way back out.
			$applied = $svc->aiUpdatePage([
				"id" => (string)$page_id,
				"changes" => ["in_nav" => true],
			], $administrator);

			T::equals((string)($applied["mode"] ?? ""), "error", "approving a staged nav change refuses too");
			T::ok(
				strpos((string)($applied["message"] ?? ""), "developer") !== false,
				"for the nav rule, not for something else that happens to fail"
			);
			T::equals(parity_nav_flag($page_id), "", "and the flag is untouched");
		} finally {
			parity_delete_page($page_id);
			parity_cleanup_tracked();
		}
	}

	/** REST pins silently rather than erroring — legacy's semantics, so no client breaks. */
	function test_parity_rest_silently_pins_a_top_level_nav_change() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[$administrator_id] = parity_nav_administrator();
		$page_id = 0;

		try {
			$page_id = parity_seed_page(["parent" => 0, "in_nav" => "", "nav_title" => "ZZ Nav REST"]);

			$svc->update(parity_request($administrator_id, 1, ["id" => $page_id], [
				"in_nav" => true,
				"nav_title" => "ZZ Nav REST Renamed",
				"publish" => true,
			], [], ["page" => [0 => "p"]]));

			T::equals(parity_nav_flag($page_id), "", "REST drops the in_nav key instead of writing it");
			T::equals(
				(string)SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $page_id),
				"ZZ Nav REST Renamed",
				"and the rest of the same write still lands — a pin, not a refusal"
			);
		} finally {
			parity_delete_page($page_id);
			parity_cleanup_tracked();
		}
	}

	/** The pending-change writer carries the clamp too (admin.php:8022's leg). */
	function test_parity_a_queued_change_cannot_carry_a_top_level_nav_flag() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[$editor_id] = parity_nav_editor();
		$page_id = 0;

		try {
			$page_id = parity_seed_page(["parent" => 0, "in_nav" => "", "nav_title" => "ZZ Nav Queued"]);

			$svc->update(parity_request($editor_id, 0, ["id" => $page_id], [
				"in_nav" => true,
				"meta_description" => "ZZ queued",
			], [], ["page" => [0 => "e"]]));

			$change = SQL::fetch(
				"SELECT changes FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?",
				$page_id
			);

			T::ok(is_array($change), "the editor's save queued a change");
			$changes = json_decode((string)$change["changes"], true) ?: [];
			T::ok(
				!array_key_exists("in_nav", $changes),
				"which carries no in_nav flag — otherwise publishing it later would apply the change nobody may make"
			);
			T::equals(
				(string)($changes["meta_description"] ?? ""),
				"ZZ queued",
				"and the rest of the queued edit survives"
			);
		} finally {
			parity_delete_page($page_id);
			parity_cleanup_tracked();
		}
	}

	/** A developer may do all of it — the rule is about level, not about the column. */
	function test_parity_a_developer_owns_the_top_level_navigation() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[, $developer] = parity_nav_developer();
		$page_id = 0;

		try {
			$page_id = parity_seed_page(["parent" => 0, "in_nav" => "", "nav_title" => "ZZ Nav Developer"]);

			$validated = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"in_nav" => true,
			], $developer);

			T::ok(!isset($validated["denied"]), "a developer can stage a top-level nav change");

			$applied = $svc->aiUpdatePage($validated["payload"], $developer);
			T::equals((string)($applied["mode"] ?? ""), "published", "and publish it");
			T::equals(parity_nav_flag($page_id), "on", "and the page is in the navigation");
		} finally {
			parity_delete_page($page_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * The clamp is about top level only, and only about `in_nav`.
	 *
	 * The admin.php:8830 semantic: an editor may work on a top-level page, they just
	 * can't change its place in the navigation — and a page below the top level has an
	 * ordinary flag anyone with edit access may set.
	 */
	function test_parity_the_clamp_is_scoped_to_top_level_pages_and_the_nav_flag() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[, $administrator] = parity_nav_administrator();
		$parent_id = 0;
		$child_id = 0;

		try {
			$parent_id = parity_seed_page(["parent" => 0, "in_nav" => "on", "nav_title" => "ZZ Nav Parent"]);
			$child_id = parity_seed_page(["parent" => $parent_id, "in_nav" => "", "nav_title" => "ZZ Nav Child"]);

			$child = $svc->aiValidatePageUpdate([
				"id" => (string)$child_id,
				"in_nav" => true,
			], $administrator);

			T::ok(!isset($child["denied"]), "a page below the top level has an ordinary nav flag");

			$unrelated = $svc->aiValidatePageUpdate([
				"id" => (string)$parent_id,
				"meta_description" => "ZZ unrelated edit",
			], $administrator);

			T::ok(
				!isset($unrelated["denied"]),
				"and an edit to a top-level page that doesn't touch in_nav is not refused"
			);

			$restating = $svc->aiValidatePageUpdate([
				"id" => (string)$parent_id,
				"in_nav" => true,
				"meta_description" => "ZZ restated",
			], $administrator);

			T::ok(
				!isset($restating["denied"]),
				"nor is one that restates the flag it already has — the rule is about changing it"
			);
		} finally {
			parity_delete_page($child_id);
			parity_delete_page($parent_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * The third leg, asserted here beside the other two: move_page still discloses
	 * rather than refusing.
	 */
	function test_parity_the_move_path_still_discloses_the_nav_consequence() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[, $administrator] = parity_nav_administrator();
		$parent_id = 0;
		$child_id = 0;

		try {
			$parent_id = parity_seed_page(["parent" => 0, "nav_title" => "ZZ Move Parent"]);
			$child_id = parity_seed_page([
				"parent" => $parent_id,
				"in_nav" => "on",
				"nav_title" => "ZZ Move Child",
			]);

			$validated = $svc->aiValidatePageMove([
				"id" => $child_id,
				"parent" => 0,
			], $administrator);

			T::ok(!isset($validated["denied"]), "moving a page to top level is allowed");
			T::ok(
				!empty($validated["preview"]["leaves_navigation"]),
				"and the card says the page will leave the navigation"
			);

			$svc->aiMovePage($validated["payload"], $administrator);
			T::equals(parity_nav_flag($child_id), "", "which is what the write then does");
		} finally {
			parity_delete_page($child_id);
			parity_delete_page($parent_id);
			parity_cleanup_tracked();
		}
	}
