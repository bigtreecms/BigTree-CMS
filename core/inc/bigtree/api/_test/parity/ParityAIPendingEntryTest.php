<?php
	/**
	 * Phase 4 (B2 / decision D2): the entry tools can address unpublished drafts.
	 *
	 * aiValidateEntryUpdate required a numeric live id and the lifecycle tools
	 * resolved live rows only, so an editor's own AI-created entry — which always
	 * lands in the pending queue — was a dead end the assistant could not
	 * subsequently fix. "Actually, change the phone number on that draft" failed, and
	 * the model couldn't explain why because the "p"-prefixed id scheme was invisible
	 * to it. REST has addressed pending entries all along.
	 */

	use BigTree\Services\AutoModuleService;

	/** An editor with edit (not publish) access to the News module. */
	function parity_pending_entry_editor(): array {
		$id = parity_seed_user(["level" => 0]);
		$user = (object)[
			"id" => $id,
			"level" => 0,
			"permissions" => ["module" => [parity_news_module_id() => "e"]],
		];

		return [$id, $user];
	}

	/** Stage + approve an editor's create, returning its "p"-prefixed draft id. */
	function parity_pending_entry_draft(AutoModuleService $svc, $user, string $title): string {
		$validated = $svc->aiValidateEntryCreate([
			"module_id" => parity_news_module_id(),
			"data" => ["title" => $title],
		], $user);

		if (empty($validated["ok"])) {

			return "";
		}

		$result = $svc->aiCreateEntry($validated["payload"], $user);

		if ((string)($result["mode"] ?? "") !== "pending") {

			return "";
		}

		return "p" . (int)($result["pending_id"] ?? 0);
	}

	function test_parity_ai_can_amend_its_own_draft_entry() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		[$editor_id, $editor] = parity_pending_entry_editor();
		$draft_id = "";

		try {
			$draft_id = parity_pending_entry_draft($svc, $editor, "zz AI Draft " . bin2hex(random_bytes(3)));
			T::ok($draft_id !== "" && $draft_id !== "p0", "editor's create landed as a draft");

			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => $draft_id,
				"data" => ["title" => "zz AI Draft Amended"],
			], $editor);

			T::ok(!empty($validated["ok"]), "the draft can be addressed for an edit");
			T::ok(!empty($validated["preview"]["is_draft"]), "the preview marks it as a draft");
			T::ok(
				strpos((string)$validated["summary"], "published live") === false,
				"the summary doesn't claim a draft edit goes live"
			);

			$result = $svc->aiUpdateEntry($validated["payload"], $editor);
			T::equals($result["mode"], "pending", "approving the edit keeps it pending");

			// The edit must amend the existing change, not queue a second one.
			$change_id = (int)substr($draft_id, 1);
			$row = SQL::fetch("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id);
			T::ok($row !== false && $row !== null, "the original draft row still exists");

			$changes = json_decode((string)$row["changes"], true);
			T::equals($changes["title"] ?? null, "zz AI Draft Amended", "the draft now carries the amended title");
		} finally {
			if ($draft_id !== "") {
				parity_delete_pending((int)substr($draft_id, 1));
			}

			parity_delete_users($editor_id);
		}
	}

	function test_parity_ai_can_discard_its_own_draft_entry() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		[$editor_id, $editor] = parity_pending_entry_editor();
		$draft_id = "";

		try {
			$draft_id = parity_pending_entry_draft($svc, $editor, "zz AI Discard " . bin2hex(random_bytes(3)));
			T::ok($draft_id !== "" && $draft_id !== "p0", "editor's create landed as a draft");

			// Discarding needs publisher access on the row, same as deleting.
			$validated = $svc->aiValidateEntryDelete([
				"module_id" => parity_news_module_id(),
				"entry_id" => $draft_id,
			], $dev);

			T::ok(!empty($validated["ok"]), "the draft can be addressed for a delete");
			T::ok(!empty($validated["preview"]["is_draft"]), "the preview marks it as a draft");
			T::ok(
				strpos((string)$validated["summary"], "cannot be undone") === false,
				"discarding a never-published draft isn't described as a permanent delete"
			);

			$result = $svc->aiDeleteEntry($validated["payload"], $dev);
			T::equals($result["mode"], "deleted", "the draft is discarded");

			$still_there = SQL::fetch("SELECT id FROM bigtree_pending_changes WHERE id = ?", (int)substr($draft_id, 1));
			T::ok(!$still_there, "the queued change row is gone");
			$draft_id = "";
		} finally {
			if ($draft_id !== "") {
				parity_delete_pending((int)substr($draft_id, 1));
			}

			parity_delete_users($dev_id, $editor_id);
		}
	}

	function test_parity_ai_refuses_flags_on_an_unpublished_draft() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		[$editor_id, $editor] = parity_pending_entry_editor();
		$draft_id = "";

		try {
			$draft_id = parity_pending_entry_draft($svc, $editor, "zz AI Flag " . bin2hex(random_bytes(3)));
			T::ok($draft_id !== "" && $draft_id !== "p0", "editor's create landed as a draft");

			// Flags live on the published row, so a draft has nothing to flip.
			$validated = $svc->aiValidateEntryFlag([
				"module_id" => parity_news_module_id(),
				"entry_id" => $draft_id,
				"flag" => "featured",
				"value" => true,
			], $dev);

			T::ok(isset($validated["error"]), "flagging a draft is refused");
			T::ok(strpos((string)$validated["error"], "draft") !== false, "the refusal explains it's still a draft");
		} finally {
			if ($draft_id !== "") {
				parity_delete_pending((int)substr($draft_id, 1));
			}

			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * Addressing a LIVE entry must keep reading the published row, even when that
	 * entry has an outstanding draft against it.
	 *
	 * getPendingItem overlays a draft onto the live row, so routing live ids through
	 * it would make the proposal diff show the draft's values as its "from" while the
	 * write goes to the published row — and, worse, run the per-row GBP check against
	 * an unpublished group value.
	 */
	function test_parity_ai_live_entry_reads_published_values_not_a_draft() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		[$editor_id, $editor] = parity_pending_entry_editor();
		$entry_id = 0;
		$change_id = 0;

		try {
			// A published entry…
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Published Title"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			// …with an editor's unpublished draft sitting against it.
			$draft = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Draft Title"],
			], $editor);
			$queued = $svc->aiUpdateEntry($draft["payload"], $editor);
			T::equals($queued["mode"], "pending", "the editor's edit queued as a draft");

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?",
				"timber_news",
				$entry_id
			);
			T::ok($change_id > 0, "a draft exists against the live entry");

			// A publisher now edits the same live entry. The diff must be against what
			// is actually published, not against the pending draft.
			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Publisher Title"],
			], $dev);

			T::ok(!empty($validated["ok"]), "the live entry validates for a publisher");
			T::ok(empty($validated["preview"]["is_draft"]), "a live id is not reported as a draft");

			$from = null;

			foreach ($validated["preview"]["fields"] as $field) {
				if (($field["column"] ?? "") === "title") {
					$from = $field["from"] ?? null;
				}
			}

			T::equals($from, "zz Published Title", "the diff's from-value is the published title, not the draft's");
		} finally {
			parity_delete_pending($change_id);
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	function test_parity_ai_rejects_a_malformed_entry_id() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			foreach (["px", "12x", "-4", "p"] as $bad) {
				$validated = $svc->aiValidateEntryUpdate([
					"module_id" => parity_news_module_id(),
					"entry_id" => $bad,
					"data" => ["title" => "x"],
				], $dev);

				T::ok(isset($validated["error"]), "\"{$bad}\" is refused as an entry id");
			}
		} finally {
			parity_delete_users($dev_id);
		}
	}
