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
	 * Addressing a LIVE entry resolves the *published* row — the per-row GBP check
	 * must never run against an unpublished group value — but the proposal's diff is
	 * measured against the queued draft when there is one, because approving it
	 * publishes that draft rather than destroying it (see aiEntryPendingChange).
	 *
	 * The card has to say so too: publishing somebody else's unreviewed work is a
	 * consequence the approver sees up front, not afterwards.
	 */
	function test_parity_ai_live_entry_publish_carries_the_queued_draft() {
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

			// A publisher now edits the same live entry. Their approval publishes the
			// queued draft along with the edit, so the diff is measured against the
			// draft — which is what the row will actually be changing from.
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

			T::equals($from, "zz Draft Title", "the diff's from-value is the draft the publish will carry forward");

			$disclosure = $validated["preview"]["publishes_draft"] ?? [];

			T::ok(!empty($disclosure), "the card discloses the draft that will be published");
			T::equals((int)($disclosure["pending_change_id"] ?? 0), $change_id, "it names the draft's change id");
			T::ok(
				in_array("title", (array)($disclosure["fields"] ?? []), true),
				"it lists the draft's changed fields"
			);
			T::ok(
				strpos((string)$validated["summary"], "unpublished draft") !== false,
				"the summary warns that approving publishes the draft too"
			);

			// The published row must end up with both edits: the publisher's title
			// wins, and the rest of the draft is carried forward rather than dropped.
			$published = $svc->aiUpdateEntry($validated["payload"], $dev);

			T::equals($published["mode"] ?? "", "published", "the publisher's approval publishes live");
			T::equals(
				SQL::fetchSingle("SELECT title FROM timber_news WHERE id = ?", $entry_id),
				"zz Publisher Title",
				"the publisher's own value wins over the draft's"
			);
			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes WHERE id = ?", $change_id),
				0,
				"the draft is consumed by the publish rather than left queued"
			);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * Mark one News form column required for the duration of a test, returning a
	 * closure that puts the module definition back exactly as it was.
	 */
	function parity_require_news_column(string $column): callable {
		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$patched = $original;

		foreach ($patched["forms"] as $form_key => $form) {
			foreach ($form["fields"] as $field_key => $field) {
				if ((string)($field["column"] ?? "") === $column) {
					$patched["forms"][$form_key]["fields"][$field_key]["settings"]["required"] = "on";
				}
			}
		}

		BigTreeJSONDB::update("modules", $module_id, $patched);

		return function () use ($module_id, $original): void {
			BigTreeJSONDB::update("modules", $module_id, $original);
		};
	}

	/**
	 * A2: update_module_entry could blank a required column.
	 *
	 * aiValidateEntryCreate gated on required fields; the update path ran no such
	 * check, so setting a required column to "" sifted cleanly, staged, and — for a
	 * publisher — published live.
	 */
	function test_parity_ai_entry_update_refuses_to_blank_a_required_column() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;
		$restore = null;

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Blankable", "blurb" => "Present"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			$restore = parity_require_news_column("blurb");

			$blanking = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["blurb" => ""],
			], $dev);

			T::ok(isset($blanking["error"]), "blanking a required column is refused at staging");
			T::ok(strpos((string)$blanking["error"], "Blurb") !== false, "the refusal names the field");

			// An unrelated edit to the same entry is still allowed.
			$unrelated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Blankable Renamed"],
			], $dev);

			T::ok(!empty($unrelated["ok"]), "an edit that leaves the required column alone still validates");
		} finally {
			if ($restore !== null) {
				$restore();
			}

			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * The gate runs again at approval, against the form as it stands then: a column
	 * made required during the proposal's 24h life would otherwise be blanked by
	 * approving a proposal that was legitimate when it was staged.
	 */
	function test_parity_ai_entry_update_re_gates_required_columns_at_approval() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;
		$restore = null;

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Drifting", "blurb" => "Present"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			// Staged while the column is still optional — a legitimate proposal.
			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["blurb" => ""],
			], $dev);

			T::ok(!empty($validated["ok"]), "clearing an optional column stages fine");

			// The form changes under the waiting proposal.
			$restore = parity_require_news_column("blurb");

			$approved = $svc->aiUpdateEntry($validated["payload"], $dev);

			T::equals($approved["mode"], "error", "approval refuses the now-invalid write");
			T::ok(strpos((string)$approved["message"], "Blurb") !== false, "and names the field");

			$stored = SQL::fetchSingle("SELECT blurb FROM timber_news WHERE id = ?", $entry_id);
			T::equals($stored, "Present", "the live row was not blanked");
		} finally {
			if ($restore !== null) {
				$restore();
			}

			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A4: a value the sift can't place used to be dropped with a bare `continue`.
	 *
	 * A complex field (an upload, a matrix) and a misspelled column were treated
	 * identically and silently: the model believed it had set the value, the user
	 * read the model's confirmation, and the preview quietly omitted it. The two
	 * cases now say different, actionable things.
	 */
	function test_parity_ai_entry_data_names_complex_and_unknown_columns() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			// `image` is a real News field the assistant can't author.
			$complex = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz Complex", "image" => "files/invented.jpg"],
			], $dev);

			T::ok(isset($complex["error"]), "a complex column is refused, not silently dropped");
			T::ok(strpos((string)$complex["error"], "image") !== false, "the error names the field");
			T::ok(strpos((string)$complex["error"], "Settable fields") !== false, "and lists what can be set");

			$typo = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz Typo", "titel" => "Misspelled"],
			], $dev);

			T::ok(isset($typo["error"]), "an unknown column is refused");
			T::ok(strpos((string)$typo["error"], "titel") !== false, "the error quotes the misspelled column");
			T::ok(strpos((string)$typo["error"], "no field") !== false, "and says it doesn't exist");
		} finally {
			parity_delete_users($dev_id);
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
