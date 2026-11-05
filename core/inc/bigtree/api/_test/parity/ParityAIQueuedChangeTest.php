<?php
	/**
	 * The behavioural half of "a queued change is a write that has not run yet, and the
	 * things it names by id can go away before it runs".
	 *
	 * E2 (A1) — a queued draft stores tag *ids*. Merging or deleting a tag rewrote the
	 * live relations and left those blobs alone, so approving the draft afterwards
	 * replayed an id that no longer named anything. On a page the consequence was
	 * silent data loss (syncTags skips the orphan and then strips the tag it was merged
	 * into, so the page ends up with no tags at all); on a module entry it was worse
	 * than silent, because BigTreeAutoModule inserts tag ids blindly and the
	 * bigtree_tags_rel foreign key rejects the orphan *after* the data UPDATE and the
	 * relation wipe have landed.
	 *
	 * E3 (A2) — deleting a page left its EDIT draft and any NEW child draft in the
	 * queue. The EDIT became permanently unpublishable and permanently listed; the NEW
	 * published *successfully* into an orphan — a live page whose parent names an id
	 * that no longer exists, in no page tree and reported by nothing.
	 */

	use BigTree\Services\PendingChangeService;
	use BigTree\Services\TagService;
	use BigTree\Api\Exceptions\BadRequestException;

	/** A throwaway tag, tracked for cleanup. */
	function parity_queued_tag(string $label): int {
		$tag = "zz queued " . $label . " " . bin2hex(random_bytes(3));
		$id = (int)SQL::insert("bigtree_tags", [
			"tag" => $tag,
			"metaphone" => metaphone($tag),
			"route" => "zz-queued-" . bin2hex(random_bytes(4)),
			"usage_count" => 0,
		]);
		parity_track_tag($id);

		return $id;
	}

	/** Attach tag ids to a record the way the live writers do. */
	function parity_queued_link_tags(string $table, $entry_id, array $tag_ids): void {
		foreach ($tag_ids as $tag_id) {
			SQL::insert("bigtree_tags_rel", [
				"table" => $table,
				"entry" => (string)$entry_id,
				"tag" => (int)$tag_id,
			]);
		}
	}

	/** The tag ids currently attached to a record, sorted. */
	function parity_queued_live_tags(string $table, $entry_id): array {
		$ids = SQL::fetchAllSingle(
			"SELECT tag FROM bigtree_tags_rel WHERE `table` = ? AND entry = ?",
			$table,
			(string)$entry_id
		);
		$ids = array_map("intval", $ids ?: []);
		sort($ids);

		return $ids;
	}

	/**
	 * A live page carrying $live tags, plus an outstanding EDIT draft that stages
	 * $staged. Written straight to the queue so the fixture is exactly the blob shape
	 * PageService::pendingChangeFields produces — tags in *both* copies.
	 *
	 * @return array{0:int,1:int} [page id, pending change id]
	 */
	function parity_queued_page_draft(int $user_id, array $live, array $staged): array {
		$page_id = parity_seed_page(["nav_title" => "ZZ Queued Tag Page", "title" => "ZZ Queued Tag Page"]);
		parity_queued_link_tags("bigtree_pages", $page_id, $live);

		$change_id = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $user_id,
			"date" => "NOW()",
			"title" => "ZZ Queued Page Tag Change",
			"table" => "bigtree_pages",
			"changes" => ["title" => "ZZ Queued Tag Page Edited", "tags" => array_values($staged)],
			"mtm_changes" => [],
			"tags_changes" => array_values($staged),
			"open_graph_changes" => [],
			"item_id" => $page_id,
			"type" => "EDIT",
			"module" => "",
			"pending_page_parent" => 0,
		]);
		parity_track_pending($change_id);

		return [$page_id, $change_id];
	}

	/**
	 * The module-entry twin: a live timber_news row carrying $live tags, plus an EDIT
	 * draft staging $staged in `tags_changes` (where an entry's tags live).
	 *
	 * @return array{0:int,1:int} [entry id, pending change id]
	 */
	function parity_queued_entry_draft(int $user_id, array $live, array $staged): array {
		$entry_id = (int)SQL::insert("timber_news", [
			"title" => "ZZ Queued Tag Entry",
			"route" => "zz-queued-entry-" . bin2hex(random_bytes(4)),
			"date" => date("Y-m-d H:i:s"),
			"image" => "",
			"blurb" => "",
			"content" => "",
		]);
		parity_track_news($entry_id);
		parity_queued_link_tags("timber_news", $entry_id, $live);

		$change_id = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $user_id,
			"date" => "NOW()",
			"title" => "ZZ Queued Entry Tag Change",
			"table" => "timber_news",
			"changes" => ["title" => "ZZ Queued Tag Entry Edited"],
			"mtm_changes" => [],
			"tags_changes" => array_values($staged),
			"open_graph_changes" => [],
			"item_id" => $entry_id,
			"type" => "EDIT",
			"module" => parity_news_module_id(),
			"pending_page_parent" => 0,
		]);
		parity_track_pending($change_id);

		return [$entry_id, $change_id];
	}

	/** True when the News fixture this file's entry legs need is present. */
	function parity_queued_ready(): bool {
		if (!parity_db_available()) {

			return false;
		}

		if (!SQL::tableExists("timber_news") || !BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News fixture unavailable)\n";

			return false;
		}

		return true;
	}

	/**
	 * E2, leg 1: the AI merge. Both record kinds keep the tag they were merged into.
	 */
	function test_parity_queued_tags_survive_an_ai_merge() {
		if (!parity_queued_ready()) {
			return;
		}

		$tags = new TagService();
		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$from = parity_queued_tag("from");
		$into = parity_queued_tag("into");

		[$page_id, $page_change] = parity_queued_page_draft($dev_id, [$from], [$from]);
		[$entry_id, $entry_change] = parity_queued_entry_draft($dev_id, [$from], [$from]);

		$merged = $tags->aiMergeTags(["into" => $into, "from" => [$from]], $dev);
		T::equals((string)($merged["mode"] ?? ""), "merged", "the AI merge ran");

		$page_result = $changes->aiPublishChange(["change_id" => $page_change], $dev);
		T::equals((string)($page_result["mode"] ?? ""), "published", "the page draft published");
		T::equals(
			parity_queued_live_tags("bigtree_pages", $page_id),
			[$into],
			"the page keeps the tag it was merged into"
		);

		$entry_result = $changes->aiPublishChange(["change_id" => $entry_change], $dev);
		T::equals((string)($entry_result["mode"] ?? ""), "published", "the entry draft published");
		T::equals(
			parity_queued_live_tags("timber_news", $entry_id),
			[$into],
			"the entry keeps the tag it was merged into"
		);
	}

	/**
	 * E2, leg 2: the same merge through the REST route TagMerge.tsx drives, so the
	 * same act leaves the same result whoever runs it.
	 */
	function test_parity_queued_tags_survive_a_rest_merge() {
		if (!parity_queued_ready()) {
			return;
		}

		$tags = new TagService();
		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$from = parity_queued_tag("rfrom");
		$into = parity_queued_tag("rinto");

		[$page_id, $page_change] = parity_queued_page_draft($dev_id, [$from], [$from]);
		[$entry_id, $entry_change] = parity_queued_entry_draft($dev_id, [$from], [$from]);

		$response = $tags->merge(parity_request($dev_id, 2, [], ["into" => $into, "from" => [$from]]));
		T::equals($response->status, 200, "the REST merge ran");

		$changes->aiPublishChange(["change_id" => $page_change], $dev);
		T::equals(
			parity_queued_live_tags("bigtree_pages", $page_id),
			[$into],
			"the page keeps the tag the REST merge moved it to"
		);

		$changes->aiPublishChange(["change_id" => $entry_change], $dev);
		T::equals(
			parity_queued_live_tags("timber_news", $entry_id),
			[$into],
			"the entry keeps the tag the REST merge moved it to"
		);
	}

	/**
	 * E2, leg 3: deleting a tag. The id comes out of both blobs, so publishing keeps
	 * the survivor and — on the entry leg — does not trip the foreign key.
	 */
	function test_parity_queued_tags_survive_a_rest_delete() {
		if (!parity_queued_ready()) {
			return;
		}

		$tags = new TagService();
		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$doomed = parity_queued_tag("doomed");
		$keeper = parity_queued_tag("keeper");

		[$page_id, $page_change] = parity_queued_page_draft($dev_id, [$doomed, $keeper], [$doomed, $keeper]);
		[$entry_id, $entry_change] = parity_queued_entry_draft($dev_id, [$doomed, $keeper], [$doomed, $keeper]);

		$response = $tags->delete(parity_request($dev_id, 2, ["id" => $doomed]));
		T::equals($response->status, 204, "the REST delete ran");

		$page_result = $changes->aiPublishChange(["change_id" => $page_change], $dev);
		T::equals((string)($page_result["mode"] ?? ""), "published", "the page draft published");
		T::equals(
			parity_queued_live_tags("bigtree_pages", $page_id),
			[$keeper],
			"the page keeps the surviving tag"
		);

		// The pre-fix failure here was an uncaught mysqli_sql_exception from
		// bigtree_tags_rel_ibfk_1, thrown after the row had already been half-published.
		$entry_result = $changes->aiPublishChange(["change_id" => $entry_change], $dev);
		T::equals((string)($entry_result["mode"] ?? ""), "published", "the entry draft published");
		T::equals(
			parity_queued_live_tags("timber_news", $entry_id),
			[$keeper],
			"the entry keeps the surviving tag"
		);
		T::ok(
			!SQL::exists("bigtree_pending_changes", $entry_change),
			"the entry's queue row was consumed rather than left to throw again"
		);
	}

	/**
	 * E2, leg 4: the third writer. Legacy admin.php deletes a tag with a bare
	 * SQL::delete, an extension can do the same, and the FK cascades the relations —
	 * none of which runs TagService's repair. The replay filter has to catch it, and
	 * has to *say* it caught it, because a silent drop is the original defect.
	 */
	function test_parity_queued_stale_tags_are_dropped_and_disclosed() {
		if (!parity_queued_ready()) {
			return;
		}

		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$doomed = parity_queued_tag("orphan");
		$keeper = parity_queued_tag("survivor");

		[$page_id, $page_change] = parity_queued_page_draft($dev_id, [$keeper], [$doomed, $keeper]);
		[$entry_id, $entry_change] = parity_queued_entry_draft($dev_id, [$keeper], [$doomed, $keeper]);

		// Exactly what admin.php:2921 does — the row goes, the FK takes the relations,
		// and the queued blobs are never touched.
		SQL::delete("bigtree_tags", $doomed);

		$card = $changes->aiValidatePublishChange(["change_id" => $page_change], $dev);
		T::ok(!empty($card["ok"]), "the publish card still stages");
		T::ok(
			strpos((string)($card["preview"]["warning"] ?? ""), "no longer exist") !== false,
			"the card warns that a staged tag is gone"
		);
		T::ok(
			strpos((string)($card["summary"] ?? ""), "no longer exist") !== false,
			"the summary says it too"
		);

		$page_result = $changes->aiPublishChange(["change_id" => $page_change], $dev);
		T::equals((string)($page_result["mode"] ?? ""), "published", "the page draft published");
		T::ok(
			strpos((string)($page_result["note"] ?? ""), "not applied") !== false,
			"the page publish result discloses the drop"
		);
		T::equals(
			parity_queued_live_tags("bigtree_pages", $page_id),
			[$keeper],
			"the page keeps the tag that still exists"
		);

		$entry_result = $changes->aiPublishChange(["change_id" => $entry_change], $dev);
		T::equals((string)($entry_result["mode"] ?? ""), "published", "the entry draft published");
		T::ok(
			strpos((string)($entry_result["note"] ?? ""), "not applied") !== false,
			"the entry publish result discloses the drop"
		);
		T::equals(
			parity_queued_live_tags("timber_news", $entry_id),
			[$keeper],
			"the entry keeps the tag that still exists"
		);
	}

	/**
	 * E3, leg 1: deleting a page takes its queued work with it — both the EDIT draft
	 * *of* it and the NEW child draft filed *under* it.
	 */
	function test_parity_deleting_a_page_clears_its_queued_changes() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new \BigTree\Services\PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$page_id = parity_seed_page(["nav_title" => "ZZ Queued Delete Target", "title" => "ZZ Queued Delete Target"]);

		$edit_change = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $dev_id,
			"date" => "NOW()",
			"title" => "ZZ Queued EDIT Draft",
			"table" => "bigtree_pages",
			"changes" => ["title" => "ZZ Edited"],
			"mtm_changes" => [],
			"tags_changes" => [],
			"open_graph_changes" => [],
			"item_id" => $page_id,
			"type" => "EDIT",
			"module" => "",
			"pending_page_parent" => 0,
		]);
		parity_track_pending($edit_change);

		$new_change = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $dev_id,
			"date" => "NOW()",
			"title" => "ZZ Queued NEW Child Draft",
			"table" => "bigtree_pages",
			"changes" => [
				"nav_title" => "ZZ Queued Child",
				"title" => "ZZ Queued Child",
				"route" => parity_unique_route("zz-queued-child"),
				"template" => "content",
			],
			"mtm_changes" => [],
			"tags_changes" => [],
			"open_graph_changes" => [],
			"item_id" => null,
			"type" => "NEW",
			"module" => "",
			"pending_page_parent" => $page_id,
		]);
		parity_track_pending($new_change);

		$pages->delete(parity_request($dev_id, 2, ["id" => $page_id]));
		parity_untrack("pages", $page_id);

		T::ok(!SQL::exists("bigtree_pages", $page_id), "the page is gone");
		T::ok(!SQL::exists("bigtree_pending_changes", $edit_change), "its EDIT draft left the queue with it");
		T::ok(!SQL::exists("bigtree_pending_changes", $new_change), "its NEW child draft left the queue with it");
	}

	/**
	 * E3, leg 2: the stranded-install case. A row already orphaned before the cleanup
	 * existed must refuse legibly rather than 404 (EDIT) or publish into an orphan
	 * (NEW). The invariant asserted is the second one: no live page may name a parent
	 * that does not exist.
	 */
	function test_parity_stranded_page_changes_refuse_rather_than_orphan() {
		if (!parity_db_available()) {
			return;
		}

		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		// An id no page has. The queue has no foreign key on either column, so this is
		// exactly the state a pre-fix page delete left behind.
		$missing = (int)SQL::fetchSingle("SELECT MAX(id) FROM bigtree_pages") + 5000;
		$child_route = parity_unique_route("zz-stranded-child");

		$edit_change = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $dev_id,
			"date" => "NOW()",
			"title" => "ZZ Stranded EDIT",
			"table" => "bigtree_pages",
			"changes" => ["title" => "ZZ Stranded"],
			"mtm_changes" => [],
			"tags_changes" => [],
			"open_graph_changes" => [],
			"item_id" => $missing,
			"type" => "EDIT",
			"module" => "",
			"pending_page_parent" => 0,
		]);
		parity_track_pending($edit_change);

		$new_change = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $dev_id,
			"date" => "NOW()",
			"title" => "ZZ Stranded NEW",
			"table" => "bigtree_pages",
			"changes" => [
				"nav_title" => "ZZ Stranded Child",
				"title" => "ZZ Stranded Child",
				"route" => $child_route,
				"template" => "content",
			],
			"mtm_changes" => [],
			"tags_changes" => [],
			"open_graph_changes" => [],
			"item_id" => null,
			"type" => "NEW",
			"module" => "",
			"pending_page_parent" => $missing,
		]);
		parity_track_pending($new_change);

		try {
			foreach ([$edit_change, $new_change] as $change_id) {
				$card = $changes->aiValidatePublishChange(["change_id" => $change_id], $dev);
				T::ok(empty($card["ok"]), "the card refuses to stage change {$change_id}");
				T::ok(
					strpos((string)($card["error"] ?? ""), "has been deleted") !== false,
					"and says why in the words the write path uses"
				);

				T::throws(
					function () use ($changes, $change_id, $dev) {
						$changes->aiPublishChange(["change_id" => $change_id], $dev);
					},
					BadRequestException::class,
					"approving change {$change_id} refuses rather than 404-ing or succeeding"
				);
			}

			// The invariant, not the message: nothing was created under a missing parent.
			T::ok(
				!SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE parent = ?", $missing),
				"no live page was created under the deleted parent"
			);
		} finally {
			$orphan = (int)SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE route = ?", $child_route);

			if ($orphan > 0) {
				parity_delete_page($orphan);
			}
		}
	}

	/**
	 * A page draft's queued tag change has to be *visible* in the page
	 * editor, or the next human save silently reverts it.
	 *
	 * applyPendingOverlay overlaid 16 fields and tags was not one of them, so PageEdit
	 * rendered the published chips while editing a draft whose staged tag set differed
	 * — and seedBody sends those chips straight back on submit. aiQueuePageTagChange
	 * is the only writer that queues a page tag change, so the assistant's queued tag
	 * edit was precisely the one an editor undid without being shown it existed.
	 */
	function test_parity_page_overlay_shows_the_drafts_tags() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new \BigTree\Services\PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$published = parity_queued_tag("published");
		$staged = parity_queued_tag("staged");

		[$page_id] = parity_queued_page_draft($dev_id, [$published], [$staged]);

		$out = parity_data($pages->get(parity_request(
			$dev_id,
			2,
			["id" => $page_id],
			[],
			["pending" => "1"]
		)));

		T::ok(!empty($out["changes_applied"]), "the draft was overlaid");
		T::equals(
			array_map("intval", array_column((array)($out["tags"] ?? []), "id")),
			[$staged],
			"the editor is shown the draft's tags, not the published ones"
		);
		T::ok(
			in_array("tags", (array)($out["changed_fields"] ?? []), true),
			"tags is marked as a pending field"
		);
		T::equals(
			array_map("intval", array_column((array)($out["pending_original"]["tags"] ?? []), "id")),
			[$published],
			"and the published set is kept for the comparison"
		);
	}

	/**
	 * The NEW-draft twin, one read seam over: presentPending returned an empty tag
	 * list, so a draft created *with* tags showed none and stampPendingNew wrote that
	 * empty list back over them on the next save.
	 */
	function test_parity_new_page_draft_presents_its_tags() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new \BigTree\Services\PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$staged = parity_queued_tag("newdraft");

		$change_id = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $dev_id,
			"date" => "NOW()",
			"title" => "ZZ Queued NEW Draft Tags",
			"table" => "bigtree_pages",
			"changes" => [
				"nav_title" => "ZZ Queued Draft",
				"title" => "ZZ Queued Draft",
				"route" => parity_unique_route("zz-draft-tags"),
				"template" => "content",
				"tags" => [$staged],
			],
			"mtm_changes" => [],
			"tags_changes" => [$staged],
			"open_graph_changes" => [],
			"item_id" => null,
			"type" => "NEW",
			"module" => "",
			"pending_page_parent" => 0,
		]);
		parity_track_pending($change_id);

		$out = parity_data($pages->getPending(parity_request($dev_id, 2, ["pcid" => $change_id])));

		T::equals(
			array_map("intval", array_column((array)($out["tags"] ?? []), "id")),
			[$staged],
			"a NEW draft presents the tags it stages"
		);
	}
