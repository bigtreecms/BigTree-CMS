<?php
	/**
	 * Phase 4 (B1 / decision D4): page revisions — list and restore.
	 *
	 * "Undo what was just done to this page" had no AI path at all, even though a
	 * restore is an ordinary page update underneath. Restore is publisher-only
	 * because it replaces live content outright and has no pending-change form.
	 */

	use BigTree\Services\PageService;

	function test_parity_ai_lists_and_restores_a_page_revision() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$page_id = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Revision " . bin2hex(random_bytes(3)),
				"title" => "Original title",
				"content" => parity_surface_default_content(),
			])["page_id"];
			T::ok($page_id > 0, "fixture page created");

			// Snapshot the original, then change the page — the shape of "someone
			// edited this and we want it back".
			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);
			$snapshot = new ReflectionMethod(PageService::class, "insertRevisionSnapshot");
			$snapshot->setAccessible(true);
			$revision_id = (int)$snapshot->invoke($svc, $page_id, $page, $user_id, "Before the rewrite");
			T::ok($revision_id > 0, "a saved revision exists");

			$edited = $svc->aiValidatePageUpdate(["id" => $page_id, "title" => "Rewritten title"], $user);
			$svc->aiUpdatePage($edited["payload"], $user);
			T::equals(
				SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id),
				"Rewritten title",
				"the page was changed"
			);

			// List: the revision the user would pick is visible and identifiable.
			$listed = $svc->aiPageRevisions($page_id, 20, $user);
			T::ok(!isset($listed["error"]), "revisions list without error");
			T::ok(count($listed["revisions"]) > 0, "the page has revisions");

			$found = null;

			foreach ($listed["revisions"] as $revision) {
				if ((int)$revision["id"] === $revision_id) {
					$found = $revision;
				}
			}

			T::ok($found !== null, "the saved revision is in the list");
			T::equals($found["description"], "Before the rewrite", "its description identifies it");
			T::ok($found["saved"], "it is marked as a deliberately saved revision");

			// Restore: staged with a diff, then applied.
			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);
			T::ok(!empty($validated["ok"]), "the restore validates");
			T::ok(isset($validated["preview"]["changes"]["title"]), "the preview shows title would change back");
			T::equals($validated["preview"]["changes"]["title"]["to"], "Original title", "…to the revision's value");

			$result = $svc->aiRestoreRevision($validated["payload"], $user);
			T::equals($result["mode"], "restored", "the restore runs");
			T::equals(
				SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id),
				"Original title",
				"the page is back to the revision's content"
			);

			// The restore must itself be reversible: the rewritten state was
			// snapshotted before being overwritten.
			$after = $svc->aiPageRevisions($page_id, 20, $user);
			$titles = array_column($after["revisions"], "title");
			T::ok(in_array("Rewritten title", $titles, true), "the pre-restore state was snapshotted");
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_revision_restore_is_publisher_only() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$dev_id, $dev] = parity_surface_user();
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => ["page" => [0 => "e"]]];
		$page_id = 0;

		try {
			$page_id = parity_surface_create($svc, $dev, [
				"parent" => 0,
				"nav_title" => "AI Revision Perms " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			])["page_id"];
			T::ok($page_id > 0, "fixture page created");

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);
			$snapshot = new ReflectionMethod(PageService::class, "insertRevisionSnapshot");
			$snapshot->setAccessible(true);
			$revision_id = (int)$snapshot->invoke($svc, $page_id, $page, $dev_id, "Snapshot");

			// An editor can stage an ordinary edit as a pending change, but a restore
			// has no pending form — it would land live — so it must be refused.
			$denied = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $editor);

			T::ok(isset($denied["denied"]), "an editor cannot stage a restore");
			T::ok(strpos((string)$denied["denied"], "publisher") !== false, "the refusal explains why");

			// A revision belonging to another page must not be restorable onto this one.
			$foreign = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id + 999999,
			], $dev);
			T::ok(isset($foreign["error"]), "a revision that isn't this page's is refused");
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}
