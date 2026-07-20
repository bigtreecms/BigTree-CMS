<?php
	/**
	 * Phase 4 (B3): update_callout / update_module, and the get_audit_trail read.
	 *
	 * The developer catalog was create-only: the assistant could produce a callout or
	 * module it could never amend, and "add a field to the promo callout" — the most
	 * likely ask in this area — had no path. get_audit_trail pairs with the phase 1
	 * descriptor work: it's what makes "what has the assistant changed?" answerable
	 * in chat rather than only in the Debug UI.
	 */

	use BigTree\Services\AuditService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\ModuleService;

	function parity_dev_user(): array {
		$id = parity_seed_user(["level" => 2]);

		return [$id, (object)["id" => $id, "level" => 2, "permissions" => []]];
	}

	function parity_delete_callout(string $id): void {
		if ($id !== "" && BigTreeJSONDB::exists("callouts", $id)) {
			BigTreeJSONDB::delete("callouts", $id);
		}
	}

	/** Create a callout directly in the json-db, bypassing the scaffold. */
	function parity_seed_callout(string $id, array $fields): void {
		BigTreeJSONDB::insert("callouts", [
			"id" => $id,
			"name" => "Parity Callout",
			"description" => "",
			"level" => 0,
			"resources" => $fields,
			"display_field" => "headline",
			"display_default" => "",
			"position" => 0,
		]);
	}

	function test_parity_ai_update_callout_adds_a_field_without_losing_the_rest() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		[$user_id, $user] = parity_dev_user();
		$callout_id = "zzparity" . bin2hex(random_bytes(3));

		try {
			parity_seed_callout($callout_id, [
				["id" => "headline", "type" => "text", "title" => "Headline", "subtitle" => "", "settings" => []],
				["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => []],
			]);

			// The read tool is what lets the model see the existing fields before
			// replacing the list — without it "add a field" means guessing the rest.
			$read = $svc->aiGetCallout($callout_id, $user);
			T::equals(count($read["callout"]["fields"]), 2, "get_callout returns the full field list");

			$validated = $svc->aiValidateCalloutUpdate([
				"id" => $callout_id,
				"fields" => [
					["id" => "headline", "type" => "text", "title" => "Headline"],
					["id" => "body", "type" => "html", "title" => "Body"],
					["id" => "link", "type" => "text", "title" => "Link"],
				],
			], $user);

			T::ok(!empty($validated["ok"]), "the field addition validates");
			T::equals($validated["preview"]["changes"]["fields_added"], "link", "the preview names the added field");
			T::ok(!isset($validated["preview"]["changes"]["fields_removed"]), "nothing is reported as removed");

			$result = $svc->aiUpdateCallout($validated["payload"], $user);
			T::equals($result["mode"], "updated", "the edit applies");

			$stored = BigTreeJSONDB::get("callouts", $callout_id);
			T::equals(count($stored["resources"]), 3, "the callout now has three fields");
			T::equals($stored["display_field"], "headline", "the display field is untouched");
		} finally {
			parity_delete_callout($callout_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_update_callout_flags_dropped_fields_and_guards_the_display_field() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		[$user_id, $user] = parity_dev_user();
		$callout_id = "zzparity" . bin2hex(random_bytes(3));

		try {
			parity_seed_callout($callout_id, [
				["id" => "headline", "type" => "text", "title" => "Headline", "subtitle" => "", "settings" => []],
				["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => []],
			]);

			// Dropping a field orphans its content wherever the callout is placed —
			// a bare field count would have hidden that from the approver.
			$dropping = $svc->aiValidateCalloutUpdate([
				"id" => $callout_id,
				"fields" => [["id" => "headline", "type" => "text", "title" => "Headline"]],
			], $user);

			T::ok(!empty($dropping["ok"]), "dropping a field is allowed");
			T::ok(
				strpos((string)$dropping["preview"]["changes"]["fields_removed"], "body") !== false,
				"the preview names the dropped field"
			);
			T::ok(
				strpos((string)$dropping["preview"]["changes"]["fields_removed"], "orphaned") !== false,
				"and says what dropping it does"
			);

			// Dropping the field the callout displays by would leave it showing
			// nothing, so it's refused rather than silently broken.
			$orphaning = $svc->aiValidateCalloutUpdate([
				"id" => $callout_id,
				"fields" => [["id" => "body", "type" => "html", "title" => "Body"]],
			], $user);

			T::ok(isset($orphaning["error"]), "dropping the display field is refused");
			T::ok(strpos((string)$orphaning["error"], "display field") !== false, "the refusal explains why");
		} finally {
			parity_delete_callout($callout_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_update_callout_is_developer_only() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		$admin_id = parity_seed_user(["level" => 1]);
		$admin = (object)["id" => $admin_id, "level" => 1, "permissions" => []];

		try {
			$denied = $svc->aiValidateCalloutUpdate(["id" => "anything", "name" => "X"], $admin);
			T::ok(isset($denied["denied"]), "an admin cannot edit callouts");

			$read_denied = $svc->aiGetCallout("anything", $admin);
			T::ok(isset($read_denied["denied"]), "an admin cannot read callout definitions either");
		} finally {
			parity_delete_users($admin_id);
		}
	}

	function test_parity_ai_update_module_renames_without_touching_the_route() {
		if (!parity_db_available()) {
			return;
		}

		if (!BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News module missing from json-db)\n";

			return;
		}

		$svc = new ModuleService();
		[$user_id, $user] = parity_dev_user();
		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);

		try {
			$validated = $svc->aiValidateModuleUpdate([
				"module_id" => $module_id,
				"name" => "zz Parity Newsroom",
			], $user);

			T::ok(!empty($validated["ok"]), "the rename validates");
			T::equals($validated["preview"]["changes"]["name"]["to"], "zz Parity Newsroom", "the preview shows the new name");

			$result = $svc->aiUpdateModule($validated["payload"], $user);
			T::equals($result["mode"], "updated", "the rename applies");

			$stored = BigTreeJSONDB::get("modules", $module_id);
			T::equals($stored["name"], "zz Parity Newsroom", "the module was renamed");
			T::equals($stored["route"], $original["route"], "the route is untouched");
			T::equals($stored["table"] ?? "", $original["table"] ?? "", "the table is untouched");

			$nothing = $svc->aiValidateModuleUpdate(["module_id" => $module_id, "name" => "zz Parity Newsroom"], $user);
			T::ok(isset($nothing["error"]), "re-stating the same name is a no-op error, not an empty proposal");

			$bad_group = $svc->aiValidateModuleUpdate(["module_id" => $module_id, "group" => "no-such-group"], $user);
			T::ok(isset($bad_group["error"]), "moving into a nonexistent group is refused");
		} finally {
			// Put the module back exactly as it was.
			BigTreeJSONDB::update("modules", $module_id, $original);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_audit_trail_read_is_admin_gated_and_filters_by_source() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new AuditService();
		[$dev_id, $dev] = parity_dev_user();
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => []];

		try {
			// One AI-approved change and one ordinary one, by the same user.
			AuditService::write("bigtree_pages", "999001", "updated", $dev_id, ["via" => "ai_assistant"]);
			AuditService::write("bigtree_pages", "999002", "updated", $dev_id, []);

			$denied = $svc->aiAuditTrail([], 25, $editor);
			T::ok(isset($denied["denied"]), "an editor cannot read the audit trail");

			$mine = $svc->aiAuditTrail(["user_id" => $dev_id], 25, $dev);
			T::equals(count($mine["entries"]), 2, "both entries are visible to an admin");

			$ai_only = $svc->aiAuditTrail(["user_id" => $dev_id, "via" => "ai_assistant"], 25, $dev);
			T::equals(count($ai_only["entries"]), 1, "the source filter narrows to AI-approved changes");
			T::equals($ai_only["entries"][0]["entry"], "999001", "and it's the right one");
			T::equals($ai_only["entries"][0]["via"], "ai_assistant", "the source is reported");

			$bad_date = $svc->aiAuditTrail(["since" => "whenever"], 25, $dev);
			T::ok(isset($bad_date["error"]), "an unparseable since date is a recoverable error");
		} finally {
			SQL::query("DELETE FROM bigtree_audit_trail WHERE user = ?", $dev_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}
