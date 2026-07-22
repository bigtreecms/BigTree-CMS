<?php
	/**
	 * Audit #5 Phase 2 (B1–B4): what happens between staging a proposal and
	 * approving it.
	 *
	 * The framework's stated convention is that a staged payload is never trusted on
	 * the way back out. These cover the three places that didn't honour it — a failed
	 * approval recorded as "Approved", a target that moved under the card, and the
	 * per-seam re-checks that were missing — plus the entry write that reported a
	 * failed INSERT as a success.
	 */

	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\ProposalFingerprint;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\AI\Tools\AbstractMutatingTool;
	use BigTree\Services\AutoModuleService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\PageService;
	use BigTree\Services\PendingChangeService;

	/** True when the proposals table is reachable in this harness. */
	function parity_a5_proposals_ready(): bool {
		if (!parity_db_available()) {

			return false;
		}

		try {
			(new ProposalStore())->ensureTable();

			return true;
		} catch (Throwable $e) {
			echo "  (skipped — proposal store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/**
	 * B1: an approval returning ["mode" => "error"] must not be recorded as approved.
	 *
	 * `failed` is a non-success status that keeps its message on the card and stays
	 * claimable, so the user can retry rather than being stranded behind a green
	 * badge over a change that never happened.
	 */
	function test_parity_ai_failed_approval_is_not_recorded_as_approved() {
		if (!parity_a5_proposals_ready()) {
			return;
		}

		$store = new ProposalStore();
		$user = ["id" => 987654002];
		$row = $store->create($user, 987654002, "update_page", "Update page.", [], ["id" => "1"]);
		$id = (string)$row["id"];

		try {
			$store->markResolved($id, ProposalStore::FAILED, ["mode" => "error", "message" => "nope"]);
			$loaded = $store->loadOwned($id, $user);

			T::equals((string)$loaded["status"], ProposalStore::FAILED, "the proposal is recorded as failed");
			T::ok(
				in_array(ProposalStore::FAILED, ProposalStore::ACTIONABLE, true),
				"a failed proposal is still actionable"
			);
			T::ok($store->claimPending($id), "a failed proposal can be claimed again for a retry");

			$presented = $store->present($store->loadOwned($id, $user));
			T::equals((string)$presented["status"], ProposalStore::APPROVING, "the retry claim moved it on");
			T::equals(
				(string)($presented["result"]["message"] ?? ""),
				"nope",
				"the failure message stays on the card"
			);
		} finally {
			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE id = ?", $id);
		}
	}

	/**
	 * B2: publish_pending_change staged only an id while pending changes collapse in
	 * place, so a publisher could approve a diff they never saw.
	 */
	function test_parity_ai_stale_pending_change_refuses_instead_of_publishing() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new PageService();
		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;
		$editor_id = 0;

		try {
			$page_id = parity_a5_page($pages, $dev);
			T::ok($page_id > 0, "fixture page created");

			[$editor_id, $editor] = parity_a5_page_editor($page_id);

			$staged = $pages->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Original draft",
			], $editor);
			$pages->aiUpdatePage($staged["payload"], $editor);

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes
				 WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$page_id
			);
			T::ok($change_id > 0, "the editor's draft is queued");

			// The publisher stages a publish of exactly that change.
			$publish = $changes->aiValidatePublishChange(["change_id" => $change_id], $dev);
			T::ok(!empty($publish["ok"]), "publish validates");
			T::ok(is_array($publish["fingerprint"] ?? null), "the proposal carries a staleness fingerprint");

			$payload = $publish["payload"];
			$payload[AbstractMutatingTool::FINGERPRINT_KEY] = [
				"descriptor" => $publish["fingerprint"],
				"hash" => ProposalFingerprint::compute($publish["fingerprint"]),
			];

			T::equals(AIChatService::stalenessError($payload), null, "an untouched change is not stale");

			// The editor rewrites the draft before the publisher clicks Approve.
			$second = $pages->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Rewritten after the card was drawn",
			], $editor);
			$pages->aiUpdatePage($second["payload"], $editor);

			$stale = AIChatService::stalenessError($payload);
			T::ok(is_array($stale), "the rewritten change is detected as stale");
			T::equals((string)($stale["mode"] ?? ""), "error", "and refuses rather than publishing");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/** B2: a page edit staged against columns that then moved is refused. */
	function test_parity_ai_stale_page_edit_is_detected() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "fixture page created");

			$staged = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "From the assistant",
			], $dev);
			T::ok(!empty($staged["ok"]), "the edit validates");

			$payload = $staged["payload"];
			$payload[AbstractMutatingTool::FINGERPRINT_KEY] = [
				"descriptor" => $staged["fingerprint"],
				"hash" => ProposalFingerprint::compute($staged["fingerprint"]),
			];

			T::equals(AIChatService::stalenessError($payload), null, "an untouched page is not stale");

			// Somebody edits the same field in the admin.
			SQL::update("bigtree_pages", $page_id, ["meta_description" => "Edited in the admin"]);

			T::ok(is_array(AIChatService::stalenessError($payload)), "the edited page is detected as stale");

			// An unrelated column moving must NOT invalidate a correct proposal.
			SQL::update("bigtree_pages", $page_id, ["meta_description" => "Edited in the admin"]);
			$fresh = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Second try",
			], $dev);
			$fresh_payload = $fresh["payload"];
			$fresh_payload[AbstractMutatingTool::FINGERPRINT_KEY] = [
				"descriptor" => $fresh["fingerprint"],
				"hash" => ProposalFingerprint::compute($fresh["fingerprint"]),
			];
			SQL::update("bigtree_pages", $page_id, ["meta_keywords" => "unrelated"]);

			T::equals(
				AIChatService::stalenessError($fresh_payload),
				null,
				"a change to a column this edit doesn't touch is not stale"
			);
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	/** A proposal with no fingerprint is never compared. */
	function test_parity_ai_staleness_ignores_unfingerprinted_payloads() {
		T::equals(AIChatService::stalenessError(["id" => "1"]), null, "no fingerprint means no comparison");
		T::equals(
			AIChatService::stalenessError([
				AbstractMutatingTool::FINGERPRINT_KEY => ["descriptor" => ["type" => "unknown"], "hash" => ""],
			]),
			null,
			"an unfingerprintable descriptor is not compared either"
		);
		T::equals(ProposalFingerprint::compute(["type" => "nope"]), "", "an unknown descriptor type hashes to nothing");
		T::equals(
			ProposalFingerprint::compute(["type" => "pending_change", "id" => 0]),
			"",
			"a missing id hashes to nothing"
		);
	}

	/**
	 * B3: approving a field-list edit and then a display_field edit staged before it
	 * left the callout pointing at a field that no longer exists.
	 */
	function test_parity_ai_callout_display_field_is_rechecked_at_approval() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		$dev = (object)["id" => 1, "level" => 2, "permissions" => []];
		$id = "zz-audit5-display-" . bin2hex(random_bytes(3));

		try {
			BigTreeJSONDB::insert("callouts", [
				"id" => $id,
				"name" => "ZZ Audit5 Display",
				"description" => "",
				"level" => 0,
				"display_field" => "title",
				"display_default" => "",
				"resources" => [
					["id" => "title", "type" => "text", "title" => "Title", "subtitle" => "", "settings" => []],
					["id" => "cta", "type" => "text", "title" => "CTA", "subtitle" => "", "settings" => []],
				],
				"position" => 0,
			]);

			// Proposal A, staged while "cta" still exists.
			$a = $svc->aiValidateCalloutUpdate(["id" => $id, "display_field" => "cta"], $dev);
			T::ok(!empty($a["ok"]), "the display_field edit validates while cta exists");

			// Proposal B drops "cta" and is approved first.
			$b = $svc->aiValidateCalloutUpdate([
				"id" => $id,
				"fields" => [["id" => "title", "type" => "text", "title" => "Title"]],
			], $dev);
			T::ok(!empty($b["ok"]), "the field-list edit validates");
			T::equals((string)$svc->aiUpdateCallout($b["payload"], $dev)["mode"], "updated", "B applies");

			// Now A. It must refuse rather than point at a field that is gone.
			$result = $svc->aiUpdateCallout($a["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "error", "A is refused at approval");

			$stored = BigTreeJSONDB::get("callouts", $id);
			T::equals((string)$stored["display_field"], "title", "the callout still names a field that exists");
		} finally {
			if (BigTreeJSONDB::exists("callouts", $id)) {
				BigTreeJSONDB::delete("callouts", $id);
			}
		}
	}

	/**
	 * B3: update_page enforced "templated or external link, never both" at staging
	 * only, so a page converted inside the TTL ended up with both set.
	 */
	function test_parity_ai_page_link_pairing_is_rechecked_at_approval() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			// A link page. Staging an external-link edit is valid right now.
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "external-link fixture page created");

			$staged = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"external" => "https://example.com/audit5-moved",
			], $dev);
			T::ok(!empty($staged["ok"]), "the link edit validates against a link page");

			// Meanwhile the page is converted to a templated page in the admin.
			SQL::update("bigtree_pages", $page_id, ["external" => "", "template" => "content"]);

			$result = $svc->aiUpdatePage($staged["payload"], $dev);
			T::equals(
				(string)($result["mode"] ?? ""),
				"error",
				"approving refuses rather than leaving the page both templated and linked"
			);

			$page = SQL::fetch("SELECT `template`, `external` FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals((string)$page["external"], "", "the external link was not written");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * B3: the entry seams wrote to `$payload["table"]` without asserting it still
	 * matched the module's own form.
	 */
	function test_parity_ai_entry_write_refuses_a_mismatched_table() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Audit5 Table Guard"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			$staged = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Audit5 Retitled"],
			], $dev);
			T::ok(!empty($staged["ok"]), "the edit validates");

			// A tampered (or simply stale) payload pointing at another table.
			$payload = $staged["payload"];
			$payload["table"] = "bigtree_pages";

			$result = $svc->aiUpdateEntry($payload, $dev);
			T::equals((string)($result["mode"] ?? ""), "error", "the write is refused, not redirected");

			$title = (string)SQL::fetchSingle("SELECT title FROM timber_news WHERE id = ?", $entry_id);
			T::equals($title, "zz Audit5 Table Guard", "the entry was not modified");
		} finally {
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * B4: createItem returns false on a failed query; casting it to 0 told the user
	 * their entry was published, with entry_id 0 and an allocation against entry 0.
	 * sanitizeData is what stops most of those failures happening at all — the same
	 * normalization PendingChangeService runs before the identical write.
	 */
	function test_parity_ai_entry_create_sanitizes_before_writing() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$module = BigTreeJSONDB::get("modules", parity_news_module_id());
		$form = is_array($module["forms"] ?? null) ? reset($module["forms"]) : null;
		$date_column = "";

		foreach ((array)($form["fields"] ?? []) as $field) {
			if (in_array((string)($field["type"] ?? ""), ["date", "datetime"], true)) {
				$date_column = (string)$field["column"];

				break;
			}
		}

		if ($date_column === "") {
			echo "  (skipped — News module has no date field to exercise sanitizeData)\n";

			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;

		try {
			$created = parity_ai_create_entry($svc, $dev, [
				"title" => "zz Audit5 Sanitize",
				$date_column => "March 3rd, 2027",
			]);

			// Either it sanitized and stored a real date, or it refused — never
			// "published" with entry_id 0.
			T::ok(
				(string)($created["mode"] ?? "") !== "published" || (int)$created["id"] > 0,
				"a publisher's create never reports published with entry_id 0"
			);

			$entry_id = (int)$created["id"];

			if ($entry_id > 0) {
				$stored = (string)SQL::fetchSingle(
					"SELECT `" . $date_column . "` FROM timber_news WHERE id = ?",
					$entry_id
				);
				T::ok(
					$stored === "" || strtotime($stored) !== false,
					"the stored date is a real date, not the model's prose"
				);
			}
		} finally {
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * The staging envelope's other half: a `lock` descriptor from a validate seam
	 * has to reach the card. AbstractMutatingTool resolves it once for every
	 * mutating tool (the same place it hashes the fingerprint), so this covers the
	 * whole catalog rather than one seam.
	 *
	 * Folded into the summary rather than added as a preview row so the model sees
	 * it too — a tool result that mentions the other editor lets the assistant say
	 * so in its reply instead of the user finding out only on the card.
	 */
	function test_parity_ai_staging_appends_a_held_lock_to_the_summary() {
		if (!parity_a5_proposals_ready()) {
			return;
		}

		$holder_id = parity_seed_user(["level" => 1, "name" => "Rae Concurrent"]);
		$actor_id = parity_seed_user(["level" => 2]);
		$actor = (object)["id" => $actor_id, "level" => 2, "permissions" => []];
		$store = new ProposalStore();
		$context = new AIToolContext($actor, 8, "987654003");
		$lock_id = 0;

		// A stand-in for any mutating tool: stageFromValidation is the shared path,
		// so what it does here is what every tool in the catalog does.
		$tool = new class ($store) extends AbstractMutatingTool {
			public function name(): string {

				return "zz_audit5_lock_probe";
			}

			public function definition($user): array {

				return $this->functionDefinition($this->name(), "test probe", []);
			}

			public function execute(array $args, AIToolContext $context): \BigTree\Services\AI\AIToolResult {

				return $this->stageFromValidation($args, $context, $this->name());
			}
		};

		try {
			$lock_id = (int)SQL::insert("bigtree_locks", [
				"table" => "config:settings",
				"item_id" => "zz-audit5-lock-probe",
				"user" => $holder_id,
				"title" => "Fixture",
			]);

			$validation = [
				"ok" => true,
				"summary" => "Change that setting.",
				"preview" => [],
				"payload" => ["id" => "zz-audit5-lock-probe"],
				"lock" => ["table" => "config:settings", "id" => "zz-audit5-lock-probe"],
			];

			$result = $tool->execute($validation, $context);
			T::equals($result->type, "proposal", "the lock does not block staging");
			T::ok(
				strpos($result->summary, "Rae Concurrent") !== false,
				"the staged summary names the holder"
			);
			T::ok(
				strpos($result->summary, "setting open in the editor") !== false,
				"and says what they have open"
			);

			// The stored proposal carries it too, so the card shows what the model saw.
			$stored = $store->loadOwned((string)$result->proposal_id, $actor);
			T::ok(
				strpos((string)$stored["summary"], "Rae Concurrent") !== false,
				"and the persisted proposal carries it to the card"
			);

			SQL::delete("bigtree_locks", $lock_id);
			$lock_id = 0;

			// Unlocked, the summary is exactly what the seam wrote.
			$clean = $tool->execute($validation, $context);
			T::equals($clean->summary, "Change that setting.", "an unlocked target adds nothing");
		} finally {
			if ($lock_id > 0) {
				SQL::delete("bigtree_locks", $lock_id);
			}

			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE conversation = ?", 987654003);
			parity_delete_users($holder_id, $actor_id);
		}
	}
