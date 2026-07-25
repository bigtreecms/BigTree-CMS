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
	use BigTree\Services\TemplateService;

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

	/** Create a template directly in the json-db, bypassing the scaffold. */
	function parity_seed_template(string $id, array $resources): void {
		BigTreeJSONDB::insert("templates", [
			"id" => $id,
			"name" => "Parity Template",
			"module" => "",
			"resources" => $resources,
			"level" => 0,
			"routed" => "",
			"hooks" => [],
			"position" => 0,
		]);
	}

	function parity_delete_template(string $id): void {
		if ($id !== "" && BigTreeJSONDB::exists("templates", $id)) {
			BigTreeJSONDB::delete("templates", $id);
		}
	}

	/**
	 * A field the assistant carries over unchanged keeps everything its own field
	 * shape can't express.
	 *
	 * Supplying `fields` replaces the whole resource list, and each field used to be
	 * rebuilt as exactly {id, type, title, subtitle} — so "add one field to this
	 * template" silently stripped validation rules, list options and image presets
	 * from every *other* field. Worst of all it stripped `required`, quietly
	 * weakening the required-field gates on every later page write.
	 */
	function test_parity_ai_update_template_preserves_untouched_field_settings() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TemplateService();
		[$user_id, $user] = parity_dev_user();
		$template_id = "zzparity" . bin2hex(random_bytes(3));
		$body_settings = [
			"validation" => "required",
			"options" => ["one" => "One", "two" => "Two"],
			"preset" => "hero",
		];

		try {
			parity_seed_template($template_id, [
				["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => $body_settings],
				["id" => "blurb", "type" => "text", "title" => "Blurb", "subtitle" => "", "settings" => []],
			]);

			$validated = $svc->aiValidateTemplateUpdate([
				"id" => $template_id,
				"fields" => [
					["id" => "body", "type" => "html", "title" => "Body"],
					["id" => "blurb", "type" => "text", "title" => "Blurb"],
					["id" => "footnote", "type" => "text", "title" => "Footnote"],
				],
			], $user);

			T::ok(!empty($validated["ok"]), "adding a field validates");
			T::ok(strpos($validated["preview"]["changes"]["fields_added"], "footnote") === 0, "the preview names the added field");
			T::ok(
				strpos($validated["preview"]["changes"]["fields_added"], "render file isn't changed") !== false,
				"and says the render file won't output it (audit #10 C1)"
			);
			T::ok(
				!isset($validated["preview"]["changes"]["no_longer_required"]),
				"carrying a required field over is not reported as losing its rule"
			);

			$svc->aiUpdateTemplate($validated["payload"], $user);

			$stored = BigTreeJSONDB::get("templates", $template_id);
			$by_id = [];

			foreach ($stored["resources"] as $resource) {
				$by_id[$resource["id"]] = $resource;
			}

			T::equals(count($stored["resources"]), 3, "the template now has three fields");
			T::equals(
				json_encode($by_id["body"]["settings"]),
				json_encode($body_settings),
				"the retained field's settings survive byte-identical"
			);
			T::equals($by_id["footnote"]["settings"], [], "a genuinely new field starts bare");
		} finally {
			parity_delete_template($template_id);
			parity_delete_users($user_id);
		}
	}

	/**
	 * The merge happens again at approval, not just at staging: a template's fields
	 * can be edited in the admin during a proposal's 24h life, and those settings
	 * have to survive the approval too.
	 */
	function test_parity_ai_update_template_re_merges_settings_at_approval() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TemplateService();
		[$user_id, $user] = parity_dev_user();
		$template_id = "zzparity" . bin2hex(random_bytes(3));

		try {
			parity_seed_template($template_id, [
				["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => []],
			]);

			$validated = $svc->aiValidateTemplateUpdate([
				"id" => $template_id,
				"fields" => [
					["id" => "body", "type" => "html", "title" => "Body"],
					["id" => "footnote", "type" => "text", "title" => "Footnote"],
				],
			], $user);

			T::ok(!empty($validated["ok"]), "the edit validates");

			// A developer makes "body" required in the admin while the proposal waits.
			$drifted = BigTreeJSONDB::get("templates", $template_id);
			$drifted["resources"][0]["settings"] = ["validation" => "required"];
			BigTreeJSONDB::update("templates", $template_id, $drifted);

			$svc->aiUpdateTemplate($validated["payload"], $user);

			$stored = BigTreeJSONDB::get("templates", $template_id);
			T::equals(
				$stored["resources"][0]["settings"]["validation"] ?? "",
				"required",
				"the rule added during the proposal's life survives approval"
			);
		} finally {
			parity_delete_template($template_id);
			parity_delete_users($user_id);
		}
	}

	/**
	 * `required` is the one setting a new AI-authored field may carry — without it
	 * an assistant-created template could never have a required field at all.
	 */
	function test_parity_ai_new_template_field_can_be_required() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TemplateService();
		[$user_id, $user] = parity_dev_user();
		$template_id = "zzparity" . bin2hex(random_bytes(3));

		try {
			parity_seed_template($template_id, [
				["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => []],
			]);

			$validated = $svc->aiValidateTemplateUpdate([
				"id" => $template_id,
				"fields" => [
					["id" => "body", "type" => "html", "title" => "Body"],
					["id" => "headline", "type" => "text", "title" => "Headline", "required" => true],
				],
			], $user);

			T::ok(!empty($validated["ok"]), "the edit validates");
			T::equals($validated["preview"]["changes"]["now_required"], "headline", "the preview discloses the new rule");

			$svc->aiUpdateTemplate($validated["payload"], $user);

			$stored = BigTreeJSONDB::get("templates", $template_id);
			T::equals(
				$stored["resources"][1]["settings"]["validation"] ?? "",
				"required",
				"the new field is stored as required"
			);
		} finally {
			parity_delete_template($template_id);
			parity_delete_users($user_id);
		}
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
			T::ok(strpos($validated["preview"]["changes"]["fields_added"], "link") === 0, "the preview names the added field");
			T::ok(
				strpos($validated["preview"]["changes"]["fields_added"], "render file isn't changed") !== false,
				"and says the render file won't output it (audit #10 C1)"
			);
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

	/**
	 * The callout half of the same problem, plus the read that makes it visible:
	 * aiGetCallout presented fields as {id, type, title, subtitle} only, so the model
	 * could not even see that a field carried configuration — let alone round-trip
	 * it — while the write path stripped it regardless.
	 */
	function test_parity_ai_update_callout_preserves_untouched_field_settings() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		[$user_id, $user] = parity_dev_user();
		$callout_id = "zzparity" . bin2hex(random_bytes(3));
		$body_settings = ["validation" => "required", "options" => ["a" => "A"]];

		try {
			parity_seed_callout($callout_id, [
				["id" => "headline", "type" => "text", "title" => "Headline", "subtitle" => "", "settings" => []],
				["id" => "body", "type" => "html", "title" => "Body", "subtitle" => "", "settings" => $body_settings],
			]);

			$read = $svc->aiGetCallout($callout_id, $user);
			$body_read = $read["callout"]["fields"][1];

			T::equals($body_read["id"], "body", "the read lists the configured field");
			T::equals(
				json_encode($body_read["settings"]),
				json_encode($body_settings),
				"get_callout exposes the field's settings"
			);
			T::ok(!empty($body_read["required"]), "and says plainly that it is required");

			$validated = $svc->aiValidateCalloutUpdate([
				"id" => $callout_id,
				"fields" => [
					["id" => "headline", "type" => "text", "title" => "Headline"],
					["id" => "body", "type" => "html", "title" => "Body"],
					["id" => "link", "type" => "text", "title" => "Link"],
				],
			], $user);

			T::ok(!empty($validated["ok"]), "the field addition validates");

			$svc->aiUpdateCallout($validated["payload"], $user);

			$stored = BigTreeJSONDB::get("callouts", $callout_id);
			T::equals(
				json_encode($stored["resources"][1]["settings"]),
				json_encode($body_settings),
				"the retained field's settings survive byte-identical"
			);
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

			// A5/D3: an unrecognised group used to be a flat "does not exist" wall with
			// no path forward. It now hands back the choice the model couldn't make.
			$bad_group = $svc->aiValidateModuleUpdate(["module_id" => $module_id, "group" => "no-such-group"], $user);
			T::ok(isset($bad_group["needs_input"]), "an unrecognised group asks rather than dead-ends");
			T::ok(
				strpos((string)$bad_group["needs_input"]["question"], "no-such-group") !== false,
				"the question quotes what was asked for"
			);
			T::ok(!empty($bad_group["needs_input"]["options"]), "and offers options to choose from");
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
