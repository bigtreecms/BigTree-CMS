<?php
	/**
	 * Audit #23 A2/E3: a template's `level` is a permission rule again.
	 *
	 * A template carries `level` (0 any editor / 1 administrators / 2 developers).
	 * Legacy enforced it in three places — both Add-Page template pickers filtered on
	 * `$template["level"] <= $this->Level` (admin.php:3646, :5656) and `updatePage()`
	 * forced a template the user isn't allowed to use back to the page's stored value
	 * (admin.php:8789). The rewrite dropped it on every path: PageService had no level
	 * check anywhere near a template, routes/pages.php declared no rule on the
	 * `template` body field, and the SPA's picker reads the unfiltered template list.
	 *
	 * That made it not an "AI is looser than REST" finding — REST had the identical
	 * hole, so every parity guard was correctly silent — but the assistant is the
	 * easiest place to reach it, because "switch this to the landing template" needs
	 * no knowledge of what the picker would have offered, and `list_templates` is a
	 * level-0 read that returns each template's level.
	 *
	 * So the guard runs both surfaces against the same rule: a level-0 editor cannot
	 * create, update or content-switch a page onto a level-2 template through the AI
	 * seams *or* through PageService::create/update, and a page already on a too-high
	 * template survives an unrelated edit by that actor — legacy's clamp semantics,
	 * where the actor may edit the page and just not its template.
	 */

	use BigTree\Services\PageService;
	use BigTree\Api\Exceptions\AuthorizationException;

	/** A level-0 editor with edit access to the whole tree. */
	function parity_level_editor(): array {
		$id = parity_seed_user(["level" => 0, "permissions" => ["page" => [0 => "e"]]]);

		return [$id, (object)["id" => $id, "level" => 0, "permissions" => ["page" => [0 => "e"]]]];
	}

	/** A developer, who may use any template. */
	function parity_level_developer(): array {
		$id = parity_seed_user(["level" => 2]);

		return [$id, (object)["id" => $id, "level" => 2, "permissions" => []]];
	}

	function test_parity_template_level_blocks_ai_page_create() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[, $editor] = parity_level_editor();
		$dev_only = "zz-level-" . bin2hex(random_bytes(3));

		try {
			parity_seed_template($dev_only, [], 2);

			$validated = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Level Create",
				"template" => $dev_only,
			], $editor);

			T::ok(isset($validated["denied"]), "an editor can't stage a page onto a developer-only template");
			T::ok(
				strpos((string)$validated["denied"], "level 2") !== false
					&& strpos((string)$validated["denied"], "level 0") !== false,
				"and the refusal names both levels, so the model can retry correctly"
			);

			// The approval half: the payload sits in the store for 24h and is never
			// trusted on the way back out, so a level raised in the meantime refuses
			// there too.
			$approved = $svc->aiCreatePage([
				"parent" => 0,
				"nav_title" => "ZZ Level Create",
				"route" => parity_unique_route(),
				"template" => $dev_only,
			], $editor);

			T::equals((string)($approved["mode"] ?? ""), "error", "and approving a staged one refuses as well");
			T::ok(
				strpos((string)($approved["message"] ?? ""), "level 2") !== false,
				"for the level, not for something else that happens to fail"
			);
		} finally {
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}

	function test_parity_template_level_blocks_rest_page_create() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$editor_id] = parity_level_editor();
		$dev_only = "zz-level-" . bin2hex(random_bytes(3));

		try {
			parity_seed_template($dev_only, [], 2);

			$request = parity_request($editor_id, 0, [], [
				"parent" => 0,
				"nav_title" => "ZZ Level REST",
				"template" => $dev_only,
			], [], ["page" => [0 => "e"]]);

			T::throws(function () use ($svc, $request) {
				$svc->create($request);
			}, AuthorizationException::class, "REST refuses the same write — this was never an AI-only rule");
		} finally {
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}

	function test_parity_template_level_blocks_ai_template_switch() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[, $editor] = parity_level_editor();
		$open = "zz-level-open-" . bin2hex(random_bytes(3));
		$dev_only = "zz-level-dev-" . bin2hex(random_bytes(3));
		$page_id = 0;

		try {
			parity_seed_template($open, [], 0);
			parity_seed_template($dev_only, [], 2);
			$page_id = parity_seed_page(["template" => $open]);

			$update = $svc->aiValidatePageUpdate([
				"id" => $page_id,
				"template" => $dev_only,
			], $editor);

			T::ok(isset($update["denied"]), "update_page can't move a page onto a template the actor can't use");

			// update_page_content is the third door onto the same rule: it takes a
			// `template` argument and switched without asking.
			$content = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"template" => $dev_only,
				"content" => ["body" => "x"],
			], $editor);

			T::ok(
				isset($content["denied"]) && strpos((string)$content["denied"], "level 2") !== false,
				"and neither can update_page_content, for the same stated reason"
			);
		} finally {
			parity_delete_page($page_id);
			parity_delete_template($open);
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}

	function test_parity_template_level_blocks_rest_template_switch() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$editor_id] = parity_level_editor();
		$open = "zz-level-open-" . bin2hex(random_bytes(3));
		$dev_only = "zz-level-dev-" . bin2hex(random_bytes(3));
		$page_id = 0;

		try {
			parity_seed_template($open, [], 0);
			parity_seed_template($dev_only, [], 2);
			$page_id = parity_seed_page(["template" => $open]);

			$request = parity_request($editor_id, 0, ["id" => $page_id], [
				"template" => $dev_only,
			], [], ["page" => [0 => "e"]]);

			T::throws(function () use ($svc, $request) {
				$svc->update($request);
			}, AuthorizationException::class, "REST refuses the switch too");
		} finally {
			parity_delete_page($page_id);
			parity_delete_template($open);
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}

	/**
	 * The admin.php:8789 semantic. A page already on a too-high template keeps it —
	 * the actor may edit everything else about the page. Legacy did that by forcing
	 * `$data["template"]` back to the stored value; refusing the whole edit instead
	 * would lock an editor out of a page they are allowed to work on.
	 */
	function test_parity_a_too_high_template_survives_an_unrelated_edit() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$editor_id, $editor] = parity_level_editor();
		$dev_only = "zz-level-dev-" . bin2hex(random_bytes(3));
		$page_id = 0;

		try {
			parity_seed_template($dev_only, [], 2);
			$page_id = parity_seed_page(["template" => $dev_only]);

			$validated = $svc->aiValidatePageUpdate([
				"id" => $page_id,
				"meta_description" => "An edit that has nothing to do with the template.",
			], $editor);

			T::ok(!empty($validated["ok"]), "an edit that doesn't touch the template still validates");
			T::ok(!isset($validated["denied"]), "and is not refused for a template it never named");

			$request = parity_request($editor_id, 0, ["id" => $page_id], [
				"meta_description" => "REST doing the same.",
			], [], ["page" => [0 => "e"]]);
			$svc->update($request);

			T::equals(
				(string)SQL::fetchSingle("SELECT template FROM bigtree_pages WHERE id = ?", $page_id),
				$dev_only,
				"the page keeps the template the actor isn't allowed to choose"
			);

			// But the template itself stays out of reach, even moving *off* it onto one
			// the actor could otherwise use — that is the half legacy's clamp carried.
			$open = "zz-level-open-" . bin2hex(random_bytes(3));
			parity_seed_template($open, [], 0);

			$switch = $svc->aiValidatePageUpdate(["id" => $page_id, "template" => $open], $editor);

			T::ok(isset($switch["denied"]), "and its template can't be changed from here at all");

			// Clearing it is a change too — converting the page into an external link
			// would take its content off a template the actor was never offered.
			$cleared = $svc->aiValidatePageUpdate([
				"id" => $page_id,
				"template" => "",
				"external" => "https://example.com/",
			], $editor);

			T::ok(isset($cleared["denied"]), "including clearing it for an external link");

			parity_delete_template($open);
		} finally {
			parity_delete_page($page_id);
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}

	/** A developer is unaffected by all of it. */
	function test_parity_a_developer_may_use_a_developer_template() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[, $developer] = parity_level_developer();
		$dev_only = "zz-level-dev-" . bin2hex(random_bytes(3));
		$page_id = 0;

		try {
			parity_seed_template($dev_only, [], 2);
			$page_id = parity_seed_page(["template" => ""]);

			$validated = $svc->aiValidatePageUpdate([
				"id" => $page_id,
				"template" => $dev_only,
			], $developer);

			T::ok(!isset($validated["denied"]), "a developer may switch a page onto a developer-only template");
		} finally {
			parity_delete_page($page_id);
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}

	/**
	 * The preselection has to agree with the rule, or create_page defaults straight
	 * into the value it is about to refuse. Legacy's picker filtered by level; the
	 * remaster's docblock claimed to mirror it and didn't, because the SPA had
	 * dropped the filter too.
	 */
	function test_parity_the_default_template_respects_the_actors_level() {
		if (!parity_db_available()) {
			return;
		}

		$ref = new ReflectionMethod(PageService::class, "aiDefaultTemplate");
		$ref->setAccessible(true);

		[, $editor] = parity_level_editor();
		[, $developer] = parity_level_developer();
		$svc = new PageService();

		try {
			// Position 0 with the templates read position-DESC puts a seeded template
			// behind the shipped ones, so the assertion is about the *filter*, not
			// about which template happens to sort first.
			$chosen = (string)$ref->invoke($svc, $editor);

			T::ok(
				$chosen === "" || (int)(BigTreeJSONDB::get("templates", $chosen)["level"] ?? 0) === 0,
				"an editor is never preselected a template they can't use"
			);

			$dev_choice = (string)$ref->invoke($svc, $developer);

			T::ok($dev_choice !== "", "a developer still gets a preselection");
		} finally {
			parity_cleanup_tracked();
		}
	}

	/**
	 * The fourth door: a revision stores its `template`, and restoring one writes it
	 * back — so a restore could reinstate a template the actor may not use. Legacy's
	 * updatePage clamp is exactly what used to stop it.
	 */
	function test_parity_a_restore_cannot_reinstate_a_too_high_template() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[, $editor] = parity_level_editor();
		$open = "zz-level-open-" . bin2hex(random_bytes(3));
		$dev_only = "zz-level-dev-" . bin2hex(random_bytes(3));
		$page_id = 0;
		$revision_id = 0;

		try {
			parity_seed_template($open, [], 0);
			parity_seed_template($dev_only, [], 2);
			$page_id = parity_seed_page(["template" => $open]);

			$revision_id = (int)SQL::insert("bigtree_page_revisions", [
				"page" => $page_id,
				"title" => "ZZ Parity Page",
				"meta_description" => "",
				"template" => $dev_only,
				"external" => "",
				"new_window" => "",
				"resources" => "",
				"author" => 0,
				"updated_at" => "NOW()",
				"saved" => "on",
				"saved_description" => "Was on the developer template",
			]);

			// A publisher grant, since restoring publishes immediately — the level
			// rule has to be what refuses this, not the publisher check.
			$publisher = (object)["id" => $editor->id, "level" => 0, "permissions" => ["page" => [0 => "p"]]];
			$validated = $svc->aiValidateRevisionRestore([
				"page_id" => $page_id,
				"revision_id" => $revision_id,
			], $publisher);

			T::ok(isset($validated["denied"]), "restoring a revision can't reinstate a template out of reach");
		} finally {
			if ($revision_id > 0) {
				SQL::delete("bigtree_page_revisions", $revision_id);
			}

			parity_delete_page($page_id);
			parity_delete_template($open);
			parity_delete_template($dev_only);
			parity_cleanup_tracked();
		}
	}
