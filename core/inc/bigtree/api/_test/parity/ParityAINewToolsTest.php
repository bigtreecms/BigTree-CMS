<?php
	/**
	 * Audit #3 (B3 / decision D2): the tools built to close catalog gaps.
	 *
	 * Each of these wrapped a REST capability the assistant could see the effects of
	 * but never reach: it could rename a page and not repair the URL, edit the fields
	 * an SEO score is computed from and not read the score, restore a revision but
	 * never create one, and place a callout in a group that had to already exist.
	 * Every one was a wall the model rediscovered by failing.
	 */

	use BigTree\Services\CalloutService;
	use BigTree\Services\FourOhFourService;
	use BigTree\Services\ModuleService;
	use BigTree\Services\PageService;

	function parity_new_tools_admin(): array {
		$id = parity_seed_user(["level" => 1]);

		return [$id, (object)["id" => $id, "level" => 1, "permissions" => []]];
	}

	function test_parity_ai_create_redirect_normalizes_and_writes() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new FourOhFourService();
		[$admin_id, $admin] = parity_new_tools_admin();
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => []];
		$source = "zz-parity-" . bin2hex(random_bytes(3));
		$redirect_id = 0;

		try {
			$denied = $svc->aiValidateRedirectCreate(["from" => "/{$source}", "to" => "/"], $editor);
			T::ok(isset($denied["denied"]), "an editor cannot create redirects");

			$validated = $svc->aiValidateRedirectCreate(["from" => "/{$source}", "to" => "/"], $admin);
			T::ok(!empty($validated["ok"]), "the redirect validates");
			T::equals($validated["preview"]["from"], "/{$source}", "the preview shows the parsed source");

			$result = $svc->aiCreateRedirect($validated["payload"], $admin);
			T::equals($result["mode"], "created", "the redirect applies");

			$redirect_id = (int)$result["id"];
			$row = SQL::fetch("SELECT * FROM bigtree_404s WHERE id = ?", $redirect_id);
			T::ok($row !== false && $row !== null, "a 404 row exists");
			T::equals((string)$row["broken_url"], $source, "the source is stored stripped of its leading slash");
			T::ok((string)$row["redirect_url"] !== "", "a destination was stored");

			// A source that parses to nothing would capture every unmatched request.
			$empty = $svc->aiValidateRedirectCreate(["from" => "/", "to" => "/somewhere"], $admin);
			T::ok(isset($empty["error"]), "a redirect from the site root is refused");

			$incomplete = $svc->aiValidateRedirectCreate(["from" => "/x", "to" => ""], $admin);
			T::ok(isset($incomplete["error"]), "a redirect with no destination is refused");
		} finally {
			if ($redirect_id) {
				SQL::delete("bigtree_404s", $redirect_id);
			}

			parity_delete_users($admin_id, $editor_id);
		}
	}

	function test_parity_ai_page_seo_rating_matches_the_admin_computation() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI SEO " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "fixture page created");

			$rating = $svc->aiPageSeoRating($page_id, $user);

			T::ok(!isset($rating["error"]), "the rating reads without error");
			T::equals($rating["page_id"], $page_id, "it names the page it rated");

			if (!empty($rating["available"])) {
				T::ok(is_int($rating["score"]), "the score is an integer");
				T::ok($rating["score"] >= 0 && $rating["score"] <= 100, "the score is within 0-100");
				T::ok(is_array($rating["recommendations"]), "recommendations come back as a list");

				// The point of the tool is to return the admin's own computation rather
				// than a second opinion.
				$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);
				$expected = PageService::getPageSEORating($page, json_decode((string)$page["resources"], true) ?: []);
				T::equals($rating["score"], (int)$expected["score"], "the score matches the admin's own");
			} else {
				T::ok(isset($rating["note"]), "an unrateable page explains itself instead of scoring 0");
			}

			$missing = $svc->aiPageSeoRating(99999999, $user);
			T::ok(isset($missing["error"]), "a nonexistent page is a recoverable error");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_save_page_revision_bookmarks_without_touching_the_page() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Revision " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "fixture page created");

			$before = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);

			// An unnamed revision is an automatic snapshot, which is exactly what this
			// tool exists to be distinguishable from.
			$unnamed = $svc->aiValidateSaveRevision(["page_id" => $page_id], $user);
			T::ok(isset($unnamed["error"]), "a revision with no description is refused");

			$validated = $svc->aiValidateSaveRevision([
				"page_id" => $page_id,
				"description" => "before the pricing rewrite",
			], $user);

			T::ok(!empty($validated["ok"]), "a described revision validates");
			T::ok(
				strpos((string)$validated["summary"], "changes nothing on the live page") !== false,
				"the summary says the live page is untouched"
			);

			$saved = $svc->aiSaveRevision($validated["payload"], $user);
			T::equals($saved["mode"], "saved", "the revision is saved");

			$revision = SQL::fetch("SELECT * FROM bigtree_page_revisions WHERE id = ?", (int)$saved["revision_id"]);
			T::equals((int)$revision["page"], $page_id, "the revision belongs to the page");
			T::equals(
				(string)$revision["saved_description"],
				"before the pricing rewrite",
				"the description is stored, marking it a deliberate save"
			);

			$after = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals($after["nav_title"], $before["nav_title"], "the live page was not modified");
			T::equals($after["resources"], $before["resources"], "the live content was not modified");

			// It must show up in the list the assistant restores from.
			$listed = $svc->aiPageRevisions($page_id, 20, $user);
			$found = false;

			foreach ($listed["revisions"] as $row) {
				if ((int)$row["id"] === (int)$saved["revision_id"]) {
					$found = true;
					T::ok(!empty($row["saved"]), "it lists as a saved revision, not an automatic one");
				}
			}

			T::ok($found, "the new revision appears in get_page_revisions");
		} finally {
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	/**
	 * A5/D3: a callout outside every group is invisible in a page region restricted
	 * to one — it "exists" and is unusable exactly where it was wanted, with no
	 * disclosure. create_callout now places it, and create_callout_group gives the
	 * "which group?" question an answer other than the groups that already exist.
	 */
	function test_parity_ai_create_callout_lands_in_its_group() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		[$user_id, $user] = parity_dev_user();
		$group_name = "zz Parity Group " . bin2hex(random_bytes(3));
		$group_id = "";
		$callout_id = "zzparity" . bin2hex(random_bytes(3));

		try {
			$group_validated = $svc->aiValidateCalloutGroupCreate(["name" => $group_name], $user);
			T::ok(!empty($group_validated["ok"]), "the group creation validates");

			$group_result = $svc->aiCreateCalloutGroup($group_validated["payload"], $user);
			T::equals($group_result["mode"], "created", "the group is created");
			$group_id = (string)$group_result["id"];

			$duplicate = $svc->aiValidateCalloutGroupCreate(["name" => $group_name], $user);
			T::ok(isset($duplicate["error"]), "a duplicate group name is refused");

			// An unrecognised group asks rather than dead-ending on "does not exist".
			$unknown = $svc->aiValidateCalloutCreate([
				"id" => $callout_id,
				"group" => "no such group",
				"fields" => [["id" => "headline", "type" => "text", "title" => "Headline"]],
			], $user);
			T::ok(isset($unknown["needs_input"]), "an unrecognised group asks which group to use");
			T::ok(!empty($unknown["needs_input"]["options"]), "and offers the existing groups");

			$validated = $svc->aiValidateCalloutCreate([
				"id" => $callout_id,
				"name" => "Parity Grouped Callout",
				"group" => $group_name,
				"fields" => [["id" => "headline", "type" => "text", "title" => "Headline"]],
			], $user);

			T::ok(!empty($validated["ok"]), "the callout validates with a group by name");
			T::equals($validated["preview"]["group"], $group_name, "the preview discloses the group");

			$created = $svc->aiCreateCallout($validated["payload"], $user);
			T::equals($created["mode"], "created", "the callout is created");

			$stored_group = BigTreeJSONDB::get("callout-groups", $group_id);
			T::ok(
				in_array($callout_id, (array)($stored_group["callouts"] ?? []), true),
				"the callout was appended to the group's callout list"
			);

			// Ungrouped is still allowed, but the card has to say what it costs.
			$ungrouped = $svc->aiValidateCalloutCreate([
				"id" => $callout_id . "b",
				"fields" => [["id" => "headline", "type" => "text", "title" => "Headline"]],
			], $user);
			T::ok(!empty($ungrouped["ok"]), "a callout with no group still validates");
			T::ok(
				strpos((string)$ungrouped["summary"], "won't belong to any callout group") !== false,
				"and the summary warns that group-restricted regions won't offer it"
			);
		} finally {
			parity_delete_callout($callout_id);

			if ($group_id !== "" && BigTreeJSONDB::exists("callout-groups", $group_id)) {
				BigTreeJSONDB::delete("callout-groups", $group_id);
			}

			parity_delete_users($user_id);
		}
	}

	/**
	 * A group can be created with its members named, rather than empty-then-four-edits
	 * (audit #9 follow-up). Membership is exclusive, so the card has to say what each
	 * callout is moving out of, and a callout deleted inside the TTL is dropped with a
	 * note rather than written in as a member that renders as nothing.
	 */
	function test_parity_ai_create_callout_group_takes_its_callouts() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new CalloutService();
		[$user_id, $user] = parity_dev_user();
		$suffix = bin2hex(random_bytes(3));
		$first = "zzgrp1" . $suffix;
		$second = "zzgrp2" . $suffix;
		$doomed = "zzgrp3" . $suffix;
		$fields = [["id" => "headline", "type" => "text", "title" => "Headline"]];
		$old_group_id = "";
		$group_id = "";
		$second_group_id = "";

		try {
			parity_seed_callout($first, $fields);
			parity_seed_callout($second, $fields);
			parity_seed_callout($doomed, $fields);

			// The first callout starts in a group, so the move is real.
			$old_group = $svc->aiValidateCalloutGroupCreate(
				["name" => "zz Old Group {$suffix}", "callouts" => [$first]],
				$user
			);
			T::ok(!empty($old_group["ok"]), "a group with a callout in it validates");
			$old_group_id = (string)$svc->aiCreateCalloutGroup($old_group["payload"], $user)["id"];
			T::ok(
				in_array($first, (array)(BigTreeJSONDB::get("callout-groups", $old_group_id)["callouts"] ?? []), true),
				"and the callout is stored as a member"
			);

			$unknown = $svc->aiValidateCalloutGroupCreate(
				["name" => "zz Unknown {$suffix}", "callouts" => ["no such callout"]],
				$user
			);
			T::ok(isset($unknown["error"]), "an unrecognised callout is refused");
			T::ok(
				isset($unknown["prior_change"]),
				"with the hint that it may be staged rather than absent"
			);

			$validated = $svc->aiValidateCalloutGroupCreate(
				["name" => "zz New Group {$suffix}", "callouts" => [$first, $second, $doomed]],
				$user
			);
			T::ok(!empty($validated["ok"]), "a group naming three callouts validates");
			T::equals(count($validated["payload"]["callouts"]), 3, "all three ride the payload");
			T::ok(
				strpos((string)($validated["preview"]["moves_out_of"] ?? ""), "zz Old Group") !== false,
				"and the card names the group the first callout is leaving"
			);

			// Deleted between staging and approval — the case the re-read exists for.
			parity_delete_callout($doomed);

			$created = $svc->aiCreateCalloutGroup($validated["payload"], $user);
			$group_id = (string)$created["id"];
			$stored = (array)(BigTreeJSONDB::get("callout-groups", $group_id)["callouts"] ?? []);

			T::equals(count($stored), 2, "the deleted callout is left out of the group");
			T::ok(in_array($first, $stored, true) && in_array($second, $stored, true), "the surviving two are members");
			T::ok(
				strpos((string)($created["note"] ?? ""), $doomed) !== false,
				"and the outcome names what was dropped"
			);

			// Exclusive membership: the first callout left its old group.
			T::ok(
				!in_array($first, (array)(BigTreeJSONDB::get("callout-groups", $old_group_id)["callouts"] ?? []), true),
				"the callout was removed from the group it was in before"
			);

			// An empty group is still the ordinary case.
			$empty = $svc->aiValidateCalloutGroupCreate(["name" => "zz Empty {$suffix}"], $user);
			T::ok(!empty($empty["ok"]), "a group with no callouts still validates");
			T::ok(
				strpos((string)$empty["summary"], "starts empty") !== false,
				"and says it starts empty"
			);
			$second_group_id = (string)$svc->aiCreateCalloutGroup($empty["payload"], $user)["id"];
		} finally {
			parity_delete_callout($first);
			parity_delete_callout($second);
			parity_delete_callout($doomed);

			foreach ([$old_group_id, $group_id, $second_group_id] as $id) {
				if ($id !== "" && BigTreeJSONDB::exists("callout-groups", $id)) {
					BigTreeJSONDB::delete("callout-groups", $id);
				}
			}

			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_create_module_group_is_developer_only() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new ModuleService();
		[$user_id, $user] = parity_dev_user();
		$admin_id = parity_seed_user(["level" => 1]);
		$admin = (object)["id" => $admin_id, "level" => 1, "permissions" => []];
		$name = "zz Parity Modgroup " . bin2hex(random_bytes(3));
		$group_id = "";

		try {
			$denied = $svc->aiValidateModuleGroupCreate(["name" => $name], $admin);
			T::ok(isset($denied["denied"]), "an admin cannot create module groups");

			$validated = $svc->aiValidateModuleGroupCreate(["name" => $name], $user);
			T::ok(!empty($validated["ok"]), "a developer can");

			$result = $svc->aiCreateModuleGroup($validated["payload"], $user);
			T::equals($result["mode"], "created", "the group is created");
			$group_id = (string)$result["id"];

			$stored = BigTreeJSONDB::get("module-groups", $group_id);
			T::equals($stored["name"], $name, "the group is stored under its name");

			// And it now answers update_module's group question.
			$duplicate = $svc->aiValidateModuleGroupCreate(["name" => $name], $user);
			T::ok(isset($duplicate["error"]), "a duplicate group name is refused");
		} finally {
			if ($group_id !== "" && BigTreeJSONDB::exists("module-groups", $group_id)) {
				BigTreeJSONDB::delete("module-groups", $group_id);
			}

			parity_delete_users($user_id, $admin_id);
		}
	}
