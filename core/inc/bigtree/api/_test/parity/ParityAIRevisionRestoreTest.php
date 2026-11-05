<?php
	/**
	 * The rules a revision restore has to re-ask.
	 *
	 * A revision is content authored in the past. Every rule the live editing seams
	 * enforce on content the model supplies — required fields present, the template
	 * exists, referenced files exist, an outstanding draft is disclosed — was true
	 * when the snapshot was taken and was re-asked by nobody when it was written
	 * back: aiValidateRevisionRestore validated the actor and the rows and then did a
	 * blind SQL::update over REVISION_COLUMNS, exactly as the REST route does.
	 *
	 * The existing gate tests couldn't see it, because they key each rule to the
	 * seams that take content *from the model* and a restore takes none.
	 *
	 * One refusal and three disclosures, on all three doors: staging, approval, REST.
	 */

	use BigTree\Api\Exceptions\ApiException;
	use BigTree\Services\PageService;

	/** A template with one required text resource, for the required-content legs. */
	function parity_restore_required_template(): string {
		$id = "zz-restore-" . bin2hex(random_bytes(3));
		parity_seed_template($id, [
			[
				"id" => "body",
				"type" => "text",
				"title" => "Body",
				"subtitle" => "",
				"settings" => ["validation" => "required"],
			],
		], 0);

		return $id;
	}

	/** A revision row on $page_id, defaulting to the shape insertRevisionSnapshot writes. */
	function parity_restore_revision(int $page_id, array $overrides = []): int {
		$id = (int)SQL::insert("bigtree_page_revisions", array_merge([
			"page" => $page_id,
			"title" => "ZZ Restore Revision",
			"meta_description" => "",
			"template" => "",
			"external" => "",
			"new_window" => "",
			"resources" => "",
			"author" => 0,
			"saved" => "on",
			"saved_description" => "Snapshot under test",
			"resource_allocation" => "[]",
			"has_deleted_resources" => "",
			"updated_at" => "NOW()",
		], $overrides));

		return $id;
	}

	/**
	 * A resource id that is certainly not in bigtree_resources — what a revision's
	 * `resource_allocation` holds once the file it named has been deleted.
	 */
	function parity_restore_deleted_resource_id(): int {

		return (int)SQL::fetchSingle("SELECT COALESCE(MAX(id), 0) FROM bigtree_resources") + 1000;
	}

	/**
	 * Leg (a), the refusal. A revision taken before a field was required has no value
	 * for it, and the restore writes its `resources` blob verbatim — so the page lands
	 * in exactly the state update_page_content's blocked-required gate and
	 * update_page's template-switch gate both refuse to produce.
	 */
	function test_parity_a_restore_cannot_empty_required_content() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$template = parity_restore_required_template();
		$page_id = 0;
		$revision_id = 0;

		try {
			$page_id = parity_seed_page([
				"template" => $template,
				"resources" => json_encode(["body" => "The live body"]),
			]);
			// The pre-required era: the snapshot has no value for `body` at all.
			$revision_id = parity_restore_revision($page_id, [
				"title" => "Before the field existed",
				"template" => $template,
				"resources" => json_encode([]),
			]);

			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);

			T::ok(isset($validated["error"]), "staging refuses a restore that would empty required content");
			T::ok(
				strpos((string)($validated["error"] ?? ""), "Body (body)") !== false,
				"and names the field, so the model can choose another revision"
			);

			// Approval re-asks it: the field can be flipped required inside the 24h a
			// staged proposal lives.
			$approved = $svc->aiRestoreRevision([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);

			T::equals((string)($approved["mode"] ?? ""), "error", "approval refuses it too");
			T::ok(
				strpos((string)($approved["message"] ?? ""), "Body (body)") !== false,
				"with the same wording"
			);

			// REST is the same rule on the same body — the assistant is never stricter
			// than the API underneath it.
			$status = 0;

			try {
				$svc->restoreRevision(parity_request($user_id, 2, ["id" => $page_id, "rev_id" => $revision_id]));
			} catch (ApiException $e) {
				$status = (int)$e->status;
			}

			T::equals($status, 422, "REST refuses it as unprocessable content");
			T::equals(
				(string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id),
				json_encode(["body" => "The live body"]),
				"and the page's content is untouched by all three"
			);
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_template($template);
			parity_delete_users($user_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * The other half of the same rule (D1): a restore is the undo path, so it is
	 * refused only when it *introduces* a gap. A page that is already incomplete can
	 * still be restored — refusing would strand it — but the card has to say the
	 * restore doesn't fix it.
	 */
	function test_parity_a_restore_onto_an_already_incomplete_page_is_disclosed() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$template = parity_restore_required_template();
		$page_id = 0;
		$revision_id = 0;

		try {
			// The live page is missing `body` too, so the restore takes nothing away.
			$page_id = parity_seed_page([
				"template" => $template,
				"resources" => json_encode(["other" => "not a template field"]),
			]);
			$revision_id = parity_restore_revision($page_id, [
				"title" => "An older, equally incomplete version",
				"template" => $template,
				"resources" => json_encode([]),
			]);

			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);

			T::ok(!empty($validated["ok"]), "the restore is allowed — the page is no less complete for it");
			T::ok(
				strpos((string)($validated["preview"]["warning"] ?? ""), "Body (body)") !== false,
				"but the card carries the warning"
			);
			T::ok(
				strpos((string)($validated["summary"] ?? ""), "already missing required content") !== false,
				"and the summary says it in a sentence"
			);

			$result = $svc->aiRestoreRevision($validated["payload"], $user);
			T::equals((string)($result["mode"] ?? ""), "restored", "and it runs");
			T::ok(
				strpos((string)($result["note"] ?? ""), "already missing required content") !== false,
				"the result says what it left behind"
			);
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_template($template);
			parity_delete_users($user_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * Leg (b). insertRevisionSnapshot stores `resource_allocation` with a comment
	 * saying it exists so a restore can't silently produce a page pointing at files
	 * that no longer exist — and then nothing on any surface read it. Computed at read
	 * time on both presenters, the card and the result (D2).
	 */
	function test_parity_a_restore_discloses_files_that_have_been_deleted() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;
		$gone = parity_restore_deleted_resource_id();

		try {
			$page_id = parity_seed_page(["title" => "ZZ Restore Files"]);
			$revision_id = parity_restore_revision($page_id, [
				"title" => "Version that used the old photo",
				"resource_allocation" => json_encode([$gone]),
			]);

			// Both presenters, in the same shape.
			$listed = $svc->aiPageRevisions($page_id, 20, $user);
			$row = null;

			foreach ($listed["revisions"] as $revision) {
				if ((int)$revision["id"] === $revision_id) {
					$row = $revision;
				}
			}

			T::ok($row !== null, "the revision is listed");
			T::equals((int)$row["missing_files"], 1, "get_page_revisions counts the deleted file");
			T::ok($row["differs_from_live"], "and says the revision still differs from the live page");

			$rest = parity_data($svc->listRevisions(parity_request($user_id, 2, ["id" => $page_id])));
			$rest_row = null;

			foreach ($rest as $revision) {
				if ((int)$revision["id"] === $revision_id) {
					$rest_row = $revision;
				}
			}

			T::ok($rest_row !== null, "REST lists it too");
			T::equals((int)$rest_row["missing_files"], 1, "with the same count");

			// The card and the result.
			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);

			T::ok(!empty($validated["ok"]), "a deleted file is a disclosure, not a refusal");
			T::ok(
				strpos((string)($validated["preview"]["missing_files"] ?? ""), "#{$gone}") !== false,
				"the card names the file that is gone"
			);
			T::ok(
				strpos((string)($validated["summary"] ?? ""), "since been deleted") !== false,
				"and the summary says so"
			);

			$result = $svc->aiRestoreRevision($validated["payload"], $user);
			T::ok(
				strpos((string)($result["note"] ?? ""), "since been deleted") !== false,
				"so does the result the model reads back"
			);
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_users($user_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * Leg (c). A template id is a soft reference everywhere in the CMS,
	 * so a revision naming a deleted template is disclosed rather than refused — but
	 * it was neither: the level rule passes a template that isn't there, and neither
	 * presenter returned the revision's template at all.
	 */
	function test_parity_a_restore_discloses_a_template_that_no_longer_exists() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;
		$gone = "zz-restore-gone-" . bin2hex(random_bytes(3));

		try {
			$page_id = parity_seed_page(["title" => "ZZ Restore Template"]);
			$revision_id = parity_restore_revision($page_id, [
				"title" => "Version on a template since deleted",
				"template" => $gone,
			]);

			$listed = $svc->aiPageRevisions($page_id, 20, $user);
			$row = null;

			foreach ($listed["revisions"] as $revision) {
				if ((int)$revision["id"] === $revision_id) {
					$row = $revision;
				}
			}

			T::ok($row !== null, "the revision is listed");
			T::equals((string)$row["template"], $gone, "the list says which template it would restore");
			T::ok(!$row["template_exists"], "and that the template is gone");

			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);

			T::ok(!empty($validated["ok"]), "a missing template is disclosed, not refused");
			T::equals(
				(string)($validated["preview"]["template_missing"] ?? ""),
				$gone,
				"the card names the template that is gone"
			);
			T::ok(
				strpos((string)($validated["summary"] ?? ""), "no longer exists") !== false,
				"and the summary says what that means"
			);
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_users($user_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * Leg (d). Both update cards resolve aiPendingEditChange and say a draft is there;
	 * the restore card never asked. The draft survives the restore and
	 * its `resources` — a whole blob authored against the pre-restore content —
	 * overlays everything the restore put back when a publisher approves it later.
	 */
	function test_parity_a_restore_card_names_an_outstanding_draft() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$editor_id = parity_seed_user(["level" => 0, "name" => "ZZ Draft Author"]);
		$page_id = 0;
		$change_id = 0;

		try {
			$page_id = parity_seed_page(["title" => "ZZ Restore Draft"]);
			$revision_id = parity_restore_revision($page_id, ["title" => "The version to go back to"]);

			$change_id = (int)SQL::insert("bigtree_pending_changes", [
				"user" => $editor_id,
				"date" => "NOW()",
				"title" => "ZZ Queued Page Edit",
				"table" => "bigtree_pages",
				"changes" => ["title" => "ZZ Draft Title"],
				"mtm_changes" => [],
				"tags_changes" => [],
				"open_graph_changes" => [],
				"item_id" => $page_id,
				"type" => "EDIT",
				"module" => "",
				"pending_page_parent" => 0,
			]);
			parity_track_pending($change_id);

			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $user);

			T::ok(!empty($validated["ok"]), "a queued draft doesn't block the restore");
			T::ok(
				strpos((string)($validated["preview"]["queued_draft"] ?? ""), "ZZ Draft Author") !== false,
				"the card names whose draft is queued"
			);
			T::ok(
				strpos((string)($validated["summary"] ?? ""), "overlay the restored content") !== false,
				"and says what approving it afterwards will do"
			);

			$result = $svc->aiRestoreRevision($validated["payload"], $user);
			T::equals((string)($result["mode"] ?? ""), "restored", "the restore runs");
			T::ok(
				SQL::exists("bigtree_pending_changes", $change_id),
				"and leaves the draft queued — the restore is not an approval of it"
			);
		} finally {
			parity_delete_pending($change_id);
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_users($user_id, $editor_id);
			parity_cleanup_tracked();
		}
	}
