<?php
	/**
	 * Phase 4 (B1): update_page_content — editing a page's template resources.
	 *
	 * update_page deliberately excluded resources, so the assistant could author a
	 * page's body at creation and never fix a typo in it afterwards. These exercise
	 * the real write path: merge semantics (an edit to one field must not blank the
	 * others), required-field enforcement, and the publisher/pending split.
	 */

	use BigTree\Services\PageService;

	/** Create a live page with the given resources, returning its id. */
	function parity_ai_content_page(PageService $svc, $user, string $template, array $content): int {
		$validated = $svc->aiValidatePageCreate([
			"parent" => 0,
			"nav_title" => "AI Content Fixture " . bin2hex(random_bytes(3)),
			"template" => $template,
			"content" => $content,
		], $user);

		if (empty($validated["ok"])) {

			return 0;
		}

		$created = $svc->aiCreatePage($validated["payload"], $user);

		return (int)($created["page_id"] ?? 0);
	}

	/** The stock "content" template's required simple fields, or [] if unavailable. */
	function parity_ai_content_required(): array {
		$template = BigTreeJSONDB::get("templates", "content");

		if (!$template) {

			return [];
		}

		$required = [];

		foreach (($template["resources"] ?? []) as $resource) {
			if (strpos((string)($resource["settings"]["validation"] ?? ""), "required") !== false) {
				$required[] = (string)$resource["id"];
			}
		}

		return $required;
	}

	function test_parity_ai_page_content_update_merges_without_blanking() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (!$required) {
			echo "  (skipped — stock content template unavailable)\n";

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$original = array_fill_keys($required, "<p>Original</p>");
			$page_id = parity_ai_content_page($svc, $user, "content", $original);
			T::ok($page_id > 0, "fixture page created");

			// Change exactly one required field; every other field must survive.
			$target = $required[0];
			$validated = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => [$target => "<p>Edited copy</p>"],
			], $user);

			T::ok(!empty($validated["ok"]), "content update validated");
			T::equals($validated["preview"]["mode"], "published", "publisher writes live");
			T::equals($validated["preview"]["fields"][0]["from"], "<p>Original</p>", "diff shows the old value");
			T::equals($validated["preview"]["fields"][0]["to"], "<p>Edited copy</p>", "diff shows the new value");

			$result = $svc->aiUpdatePageContent($validated["payload"], $user);
			T::equals($result["mode"], "published", "written live");

			$stored = json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id), true);
			T::equals($stored[$target] ?? null, "<p>Edited copy</p>", "the edited field was updated");

			foreach ($required as $field) {
				if ($field === $target) {
					continue;
				}

				T::equals($stored[$field] ?? null, "<p>Original</p>", "untouched field {$field} kept its value");
			}
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_ai_page_content_update_refuses_blanking_a_required_field() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (!$required) {
			echo "  (skipped — stock content template unavailable)\n";

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$page_id = parity_ai_content_page($svc, $user, "content", array_fill_keys($required, "<p>Original</p>"));
			T::ok($page_id > 0, "fixture page created");

			$blanked = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => [$required[0] => ""],
			], $user);

			T::ok(isset($blanked["error"]), "blanking a required field is refused");
			T::ok(strpos($blanked["error"], "required") !== false, "the error says why");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_ai_page_content_update_rejects_unknown_and_missing_content() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (!$required) {
			echo "  (skipped — stock content template unavailable)\n";

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$page_id = parity_ai_content_page($svc, $user, "content", array_fill_keys($required, "<p>Original</p>"));

			// No content at all → schema discovery, nothing staged.
			$none = $svc->aiValidatePageContentUpdate(["id" => $page_id], $user);
			T::ok(isset($none["error"]), "no content is a recoverable error");
			T::ok(strpos($none["error"], "Settable fields") !== false, "it lists the settable fields");

			// Only fields the assistant can't set → refused rather than a silent no-op.
			$complex = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => ["page_image" => "files/invented.jpg"],
			], $user);
			T::ok(isset($complex["error"]), "a complex-only edit is refused, not silently dropped");

			$missing = $svc->aiValidatePageContentUpdate(["id" => 99999999, "content" => ["x" => "y"]], $user);
			T::ok(isset($missing["error"]), "a nonexistent page is an error");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A2: proposals live up to 24h. The merge must happen at approval, against the
	 * page as it stands then — not at staging, which would write a stale snapshot
	 * over any edit made in between, including fields the assistant never touched.
	 */
	function test_parity_ai_page_content_approval_keeps_interim_edits() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (count($required) < 2) {
			echo "  (skipped — needs a template with two required simple fields)\n";

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$page_id = parity_ai_content_page($svc, $user, "content", array_fill_keys($required, "<p>Original</p>"));
			T::ok($page_id > 0, "fixture page created");

			// Stage a change to field A…
			$validated = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => [$required[0] => "<p>Assistant's edit</p>"],
			], $user);
			T::ok(!empty($validated["ok"]), "content update validated");

			// …then someone edits field B directly, the way a human in the admin would.
			$interim = json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id), true);
			$interim[$required[1]] = "<p>Someone else's edit</p>";
			SQL::update("bigtree_pages", $page_id, ["resources" => json_encode($interim)]);

			$result = $svc->aiUpdatePageContent($validated["payload"], $user);
			T::equals($result["mode"], "published", "approval still writes live");

			$stored = json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id), true);
			T::equals($stored[$required[0]] ?? null, "<p>Assistant's edit</p>", "the proposed field was applied");
			T::equals($stored[$required[1]] ?? null, "<p>Someone else's edit</p>", "the interim edit survived approval");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A3/A2: the content gates re-run at approval, not just at staging. If the page
	 * drifted such that the merged result no longer validates, approval must return
	 * mode:error rather than writing it — mirroring what settings already do.
	 */
	function test_parity_ai_page_content_approval_rechecks_required_fields() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (count($required) < 2) {
			echo "  (skipped — needs a template with two required simple fields)\n";

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$page_id = parity_ai_content_page($svc, $user, "content", array_fill_keys($required, "<p>Original</p>"));

			$validated = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => [$required[0] => "<p>Assistant's edit</p>"],
			], $user);
			T::ok(!empty($validated["ok"]), "content update validated");

			// A required field the proposal doesn't touch is emptied in the meantime,
			// so the re-merged result is incomplete.
			$interim = json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id), true);
			$interim[$required[1]] = "";
			SQL::update("bigtree_pages", $page_id, ["resources" => json_encode($interim)]);

			$result = $svc->aiUpdatePageContent($validated["payload"], $user);
			T::equals($result["mode"], "error", "approval refuses an incomplete merged result");
			T::ok(strpos((string)$result["message"], "required") !== false, "the error names the problem");

			$stored = json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id), true);
			T::equals($stored[$required[0]] ?? null, "<p>Original</p>", "nothing was written");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_ai_page_content_update_queues_pending_for_editor() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (!$required) {
			echo "  (skipped — stock content template unavailable)\n";

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => ["page" => [0 => "e"]]];
		$page_id = 0;
		$change_id = 0;

		try {
			$page_id = parity_ai_content_page($svc, $dev, "content", array_fill_keys($required, "<p>Original</p>"));
			T::ok($page_id > 0, "fixture page created");

			$validated = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => [$required[0] => "<p>Editor's edit</p>"],
			], $editor);

			T::ok(!empty($validated["ok"]), "editor's content update validates");
			T::equals($validated["preview"]["mode"], "pending", "an editor's write is queued, not live");

			$result = $svc->aiUpdatePageContent($validated["payload"], $editor);
			T::equals($result["mode"], "pending", "approval queues a pending change");
			$change_id = (int)($result["pending_change_id"] ?? 0);
			T::ok($change_id > 0, "pending change id returned");

			// The live page must be untouched until a publisher approves.
			$stored = json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id), true);
			T::equals($stored[$required[0]] ?? null, "<p>Original</p>", "live page unchanged while pending");
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}
