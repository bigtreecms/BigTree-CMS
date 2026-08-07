<?php
	/**
	 * Audit #19 guard E3: the resource fill rule applies on every page write path.
	 *
	 * `PageService::normalizePageResources` fills every template resource key missing
	 * from a page's stored `resources` blob — with the field's `default` setting, or
	 * "" — so front-end templates stop hitting undefined variables. There are three
	 * writers of that blob (performCreate, performUpdate, pendingChangeFields) and
	 * A2/A3 were both the same class of bug: a fill rule applied on one path and not
	 * its twin.
	 *
	 * A3 specifically: the queued path normalized against `$changes["template"]`, and
	 * an EDIT blob holds only the keys being changed — so an editor's content edit
	 * normalized against "" and did nothing, while the same proposal approved by a
	 * publisher normalized fine. The blob is what the SPA's "Compare with published"
	 * diff reads and what aiOverlayPendingEdit diffs against.
	 */

	use BigTree\Services\PageService;

	/**
	 * A throwaway template: one plain field, one declaring a `default`, and one
	 * image reference — the third is what resource allocation keys on, and it only
	 * resolves when the template does.
	 */
	function parity_normalization_template(): string {
		$id = "zz-parity-normalize-" . strtolower(bin2hex(random_bytes(3)));

		parity_seed_template($id, [
			["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => []],
			[
				"id" => "sidebar",
				"type" => "textarea",
				"title" => "Sidebar",
				"subtitle" => "",
				"settings" => ["default" => "Default sidebar copy"],
			],
			["id" => "hero", "type" => "image-reference", "title" => "Hero", "subtitle" => "", "settings" => []],
		]);

		return $id;
	}

	/** A throwaway image row in the Files library. */
	function parity_normalization_resource(): int {

		return (int)SQL::insert("bigtree_resources", [
			"file" => "zz-parity-normalize-" . bin2hex(random_bytes(4)) . ".png",
			"name" => "ZZ Normalize fixture",
			"date" => "NOW()",
			"is_image" => "on",
			"metadata" => "",
			"crops" => "",
			"thumbs" => "",
		]);
	}

	/** Drop a throwaway resource and everything allocated against it. */
	function parity_normalization_delete_resource(int $resource_id): void {
		if ($resource_id < 1) {

			return;
		}

		SQL::query("DELETE FROM bigtree_resource_allocation WHERE resource = ?", $resource_id);
		SQL::delete("bigtree_resources", $resource_id);
	}

	/**
	 * A live create fills the untouched key from its field's `default`.
	 *
	 * This is also the only end-to-end proof that `default` is a setting the product
	 * honours — audit #19 A2 found both of its readers in place and nothing in the
	 * CMS able to author the key they read.
	 */
	function test_parity_a_live_page_create_fills_untouched_resources_from_their_defaults() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$template = parity_normalization_template();
		$page_id = 0;

		try {
			$validated = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Normalize " . bin2hex(random_bytes(3)),
				"template" => $template,
				"content" => ["body" => "<p>Body copy</p>"],
			], $dev);

			T::ok(!empty($validated["ok"]), "the create validates");
			$page_id = (int)($svc->aiCreatePage($validated["payload"], $dev)["page_id"] ?? 0);
			T::ok($page_id > 0, "the page was created");

			$stored = json_decode(
				(string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $page_id),
				true
			);

			T::equals($stored["body"] ?? null, "<p>Body copy</p>", "the authored field kept its value");
			T::equals(
				$stored["sidebar"] ?? null,
				"Default sidebar copy",
				"and the untouched field was filled from its `default` setting"
			);
		} finally {
			parity_delete_page($page_id);
			parity_delete_template($template);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A3: an editor's queued content edit normalizes too, even though the change
	 * blob says nothing about the template.
	 */
	function test_parity_a_queued_page_edit_normalizes_against_the_pages_own_template() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => ["page" => [0 => "e"]]];
		$template = parity_normalization_template();
		$page_id = 0;
		$change_id = 0;

		try {
			// Only `body` is stored: the page predates the template's second resource,
			// which is the gap normalization exists to close. Seeding both would let
			// the edit carry `sidebar` forward from the live row and the test would
			// pass whether or not anything normalized.
			$page_id = parity_seed_page([
				"template" => $template,
				"resources" => json_encode(["body" => "<p>Body copy</p>"]),
			]);

			$validated = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => ["body" => "<p>Editor's edit</p>"],
			], $editor);

			T::ok(!empty($validated["ok"]), "the editor's content update validates");
			$result = $svc->aiUpdatePageContent($validated["payload"], $editor);
			T::equals((string)($result["mode"] ?? ""), "pending", "an editor's write is queued");

			$change_id = (int)($result["pending_change_id"] ?? 0);
			T::ok($change_id > 0, "a queued change was written");

			$changes = json_decode(
				(string)SQL::fetchSingle("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id),
				true
			);
			$resources = is_array($changes["resources"] ?? null) ? $changes["resources"] : [];

			T::ok(
				!array_key_exists("template", $changes),
				"the change blob doesn't restate the template — which is the case A3 is about"
			);
			T::equals($resources["body"] ?? null, "<p>Editor's edit</p>", "the edited field is queued");
			T::equals(
				$resources["sidebar"] ?? null,
				"Default sidebar copy",
				"and the key the live page never had is filled from its `default` in the queued blob"
			);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($page_id);
			parity_delete_template($template);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * The other reader of the same blob against the same template: resource
	 * allocation.
	 *
	 * `allocatePageResources` resolves the template to get the *reference keys* — the
	 * ids of the reference-typed fields — and a bare numeric resource id is only
	 * tracked when its key is among them. Against an empty template id there are no
	 * reference keys, so an editor's queued edit allocated nothing and the image it
	 * queued dropped to zero usage in Files, where anyone could delete it. Same
	 * fallback as the normalization above, three lines below it.
	 */
	function test_parity_a_queued_page_edit_allocates_its_resources_against_that_template() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => ["page" => [0 => "e"]]];
		$template = parity_normalization_template();
		$resource_id = parity_normalization_resource();
		$page_id = 0;
		$change_id = 0;

		try {
			$page_id = parity_seed_page([
				"template" => $template,
				"resources" => json_encode(["body" => "<p>Body copy</p>"]),
			]);

			$validated = $svc->aiValidatePageContentUpdate([
				"id" => $page_id,
				"content" => ["hero" => (string)$resource_id],
			], $editor);

			T::ok(!empty($validated["ok"]), "the editor's image edit validates");
			$result = $svc->aiUpdatePageContent($validated["payload"], $editor);
			T::equals((string)($result["mode"] ?? ""), "pending", "an editor's write is queued");

			$change_id = (int)($result["pending_change_id"] ?? 0);
			T::ok($change_id > 0, "a queued change was written");

			T::equals(
				(int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ? AND resource = ?",
					"bigtree_pages",
					"p" . $change_id,
					$resource_id
				),
				1,
				"the queued image is allocated to the change, so Files counts it as in use"
			);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($page_id);
			parity_delete_template($template);
			parity_normalization_delete_resource($resource_id);
			parity_delete_users($editor_id);
		}
	}
