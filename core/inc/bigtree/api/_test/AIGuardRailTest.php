<?php
	/**
	 * Phase 2 (A4, A5-adjacent, A7): guard rails on the values the assistant proposes.
	 *
	 * These all cover the same class of bug — a validate step that accepted whatever
	 * shape the model produced, leaving the malformed value to fail (or silently
	 * misrender) much later, at render time or not at all.
	 *
	 * DB-free where possible: the value/type checks are pure functions of a
	 * definition, driven directly via reflection.
	 */

	use BigTree\Services\SettingService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\UserService;

	function ai_guard_invoke(object $object, string $method, array $args) {
		$ref = new ReflectionMethod($object, $method);
		$ref->setAccessible(true);

		return $ref->invokeArgs($object, $args);
	}

	/** A static "list" setting definition with the given options. */
	function ai_guard_list_setting(array $values, string $allow_empty = "Yes"): array {
		$list = [];

		foreach ($values as $value) {
			$list[] = ["value" => $value, "description" => ucfirst((string)$value)];
		}

		return [
			"id" => "zz-guard-list",
			"name" => "Guard List",
			"type" => "list",
			"settings" => ["list_type" => "static", "allow-empty" => $allow_empty, "list" => $list],
		];
	}

	function test_ai_guard_setting_select_rejects_value_outside_options() {
		$svc = new SettingService();
		$def = ai_guard_list_setting(["red", "green", "blue"]);

		$bad = ai_guard_invoke($svc, "aiCheckSettingValue", [$def, "purple"]);
		T::ok(isset($bad["error"]), "a value outside the option list is refused");
		T::ok(strpos($bad["error"], "red") !== false, "the error lists the valid options");

		$good = ai_guard_invoke($svc, "aiCheckSettingValue", [$def, "green"]);
		T::equals($good["value"] ?? null, "green", "a listed option is accepted");
	}

	function test_ai_guard_setting_list_empty_respects_allow_empty() {
		$svc = new SettingService();

		$allowed = ai_guard_invoke($svc, "aiCheckSettingValue", [ai_guard_list_setting(["a", "b"]), ""]);
		T::equals($allowed["value"] ?? null, "", "blank accepted when the list allows empty");

		$refused = ai_guard_invoke($svc, "aiCheckSettingValue", [ai_guard_list_setting(["a", "b"], "No"), ""]);
		T::ok(isset($refused["error"]), "blank refused when the list disallows empty");
	}

	function test_ai_guard_setting_scalar_type_rejects_array() {
		$svc = new SettingService();
		$def = ["id" => "zz-guard-text", "name" => "Guard Text", "type" => "text", "settings" => []];

		$bad = ai_guard_invoke($svc, "aiCheckSettingValue", [$def, ["a", "b"]]);
		T::ok(isset($bad["error"]), "an array for a scalar setting is refused");
		T::ok(strpos($bad["error"], "not a list") !== false, "the error explains the shape mismatch");

		$good = ai_guard_invoke($svc, "aiCheckSettingValue", [$def, "plain string"]);
		T::equals($good["value"] ?? null, "plain string", "a scalar is accepted");
	}

	function test_ai_guard_setting_refuses_unauthorable_types() {
		$svc = new SettingService();

		foreach (["image", "upload", "matrix", "callouts", "media-gallery"] as $type) {
			$def = ["id" => "zz-guard-{$type}", "name" => "Guard " . $type, "type" => $type, "settings" => []];
			$result = ai_guard_invoke($svc, "aiCheckSettingValue", [$def, "files/made-up.jpg"]);

			T::ok(isset($result["error"]), "a {$type} setting cannot be authored by the assistant");
		}
	}

	function test_ai_guard_setting_coerces_checkbox_and_validates_numbers() {
		$svc = new SettingService();
		$checkbox = ["id" => "zz-cb", "name" => "Guard Checkbox", "type" => "checkbox", "settings" => []];

		T::equals(ai_guard_invoke($svc, "aiCheckSettingValue", [$checkbox, true])["value"], "on", "true → on");
		T::equals(ai_guard_invoke($svc, "aiCheckSettingValue", [$checkbox, false])["value"], "", "false → empty");
		T::equals(ai_guard_invoke($svc, "aiCheckSettingValue", [$checkbox, "false"])["value"], "", "\"false\" → empty");
		T::equals(ai_guard_invoke($svc, "aiCheckSettingValue", [$checkbox, "0"])["value"], "", "\"0\" → empty");

		$number = ["id" => "zz-num", "name" => "Guard Number", "type" => "number", "settings" => []];
		T::ok(isset(ai_guard_invoke($svc, "aiCheckSettingValue", [$number, "abc"])["error"]), "non-numeric refused");
		T::equals(ai_guard_invoke($svc, "aiCheckSettingValue", [$number, "42"])["value"], 42, "numeric string coerced");
	}

	/**
	 * A6: date/datetime/time and email settings fell through to "store as given",
	 * so "next Tuesday" landed verbatim in a date setting — the page-schedule path
	 * rejects exactly that with a helpful message, and so should this.
	 */
	function test_ai_guard_setting_normalizes_dates_and_validates_email() {
		$svc = new SettingService();

		$date = ["id" => "zz-date", "name" => "Guard Date", "type" => "date", "settings" => []];
		$vague = ai_guard_invoke($svc, "aiCheckSettingValue", [$date, "sometime next Tuesday-ish"]);
		T::ok(isset($vague["error"]), "an unparseable date is refused rather than stored verbatim");
		T::ok(strpos($vague["error"], "Guard Date") !== false, "the error names the setting");

		T::equals(
			ai_guard_invoke($svc, "aiCheckSettingValue", [$date, "2030-08-01"])["value"],
			"2030-08-01",
			"an explicit date is stored normalized"
		);

		$datetime = ["id" => "zz-dt", "name" => "Guard DateTime", "type" => "datetime", "settings" => []];
		T::equals(
			ai_guard_invoke($svc, "aiCheckSettingValue", [$datetime, "2030-08-01 09:30"])["value"],
			"2030-08-01 09:30:00",
			"a datetime is normalized to the stored format"
		);

		$time = ["id" => "zz-time", "name" => "Guard Time", "type" => "time", "settings" => []];
		T::ok(
			isset(ai_guard_invoke($svc, "aiCheckSettingValue", [$time, "half past nine-ish"])["error"]),
			"an unparseable time is refused"
		);

		// Clearing a date setting stays legal — emptiness isn't this check's business.
		T::equals(ai_guard_invoke($svc, "aiCheckSettingValue", [$date, ""])["value"], "", "a date can still be cleared");

		$email = ["id" => "zz-email", "name" => "Guard Email", "type" => "email", "settings" => []];
		T::ok(
			isset(ai_guard_invoke($svc, "aiCheckSettingValue", [$email, "not an address"])["error"]),
			"an invalid email is refused"
		);
		T::equals(
			ai_guard_invoke($svc, "aiCheckSettingValue", [$email, "hi@example.com"])["value"],
			"hi@example.com",
			"a valid email is accepted"
		);
	}

	function test_ai_guard_callout_display_field_must_exist() {
		$svc = new CalloutService();
		$fields = [
			["id" => "heading", "type" => "text", "title" => "Heading"],
			["id" => "body", "type" => "html", "title" => "Body"],
		];

		$bad = ai_guard_invoke($svc, "aiResolveDisplayField", ["headline", $fields]);
		T::ok(isset($bad["error"]), "a display_field naming no real field is refused");
		T::ok(strpos($bad["error"], "heading") !== false, "the error lists the real field ids");

		$good = ai_guard_invoke($svc, "aiResolveDisplayField", ["body", $fields]);
		T::equals($good["value"] ?? null, "body", "a real field id is accepted");
	}

	function test_ai_guard_callout_display_field_defaults_to_first_text_field() {
		$svc = new CalloutService();

		// The developer UI's convention: label instances by the first text field.
		$fields = [
			["id" => "body", "type" => "html", "title" => "Body"],
			["id" => "heading", "type" => "text", "title" => "Heading"],
		];
		T::equals(
			ai_guard_invoke($svc, "aiResolveDisplayField", ["", $fields])["value"],
			"heading",
			"defaults to the first text field, not simply the first field"
		);

		// No text field at all — fall back to the first field so instances still label.
		$no_text = [["id" => "body", "type" => "html", "title" => "Body"]];
		T::equals(
			ai_guard_invoke($svc, "aiResolveDisplayField", ["", $no_text])["value"],
			"body",
			"falls back to the first field when there is no text field"
		);

		T::equals(ai_guard_invoke($svc, "aiResolveDisplayField", ["", []])["value"], "", "no fields → empty");
	}

	function test_ai_guard_callout_rejects_unknown_field_types() {
		$svc = new CalloutService();

		$bad = ai_guard_invoke($svc, "aiInvalidFieldTypeError", [[
			["id" => "heading", "type" => "text"],
			["id" => "body", "type" => "wysiwyg"],
		]]);

		T::ok($bad !== null, "a model-invented field type is refused");
		T::ok(strpos((string)$bad, "wysiwyg") !== false, "the error names the bad type");
		T::ok(strpos((string)$bad, "text") !== false, "the error lists the available types");

		$good = ai_guard_invoke($svc, "aiInvalidFieldTypeError", [[["id" => "heading", "type" => "text"]]]);
		T::equals($good, null, "installed types pass");
	}

	function test_ai_guard_user_create_rejects_bad_timezone() {
		$svc = new UserService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];

		$bad = $svc->aiValidateUserCreate([
			"email" => "zz-guard-" . bin2hex(random_bytes(4)) . "@example.com",
			"name" => "Guard Fixture",
			"timezone" => "Mars/Olympus_Mons",
		], $admin);

		T::ok(isset($bad["error"]), "an invalid timezone identifier is refused");
		T::ok(strpos($bad["error"], "timezone") !== false, "the error explains what's wrong");
	}

	function test_ai_guard_user_create_preview_warns_about_login_and_invite() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];
		$email = "zz-guard-" . bin2hex(random_bytes(4)) . "@example.com";

		$ok = $svc->aiValidateUserCreate([
			"email" => $email,
			"name" => "Guard Fixture",
			"timezone" => "America/New_York",
		], $admin);

		T::ok(!empty($ok["ok"]), "a valid timezone validates");
		T::ok(strpos($ok["summary"], "cannot log in") !== false, "the summary states the account can't log in yet");
		T::ok(!empty($ok["preview"]["sends_invite_email"]), "the preview flags that approval sends an email");
		T::ok(strpos((string)$ok["preview"]["note"], $email) !== false, "the preview names the invite recipient");
	}
