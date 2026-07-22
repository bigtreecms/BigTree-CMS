<?php
	/**
	 * Audit #4 Phase 1 (A1, A2, A4): values the assistant could still write that
	 * neither the admin UI nor the REST validators would ever produce.
	 *
	 * All three share a shape — a create path that validated something the matching
	 * update path didn't, or a field stored verbatim because REST happened to be
	 * loose about it. Each guard is asserted twice: at staging (where the model can
	 * still correct itself) and at approval (where the stored payload is never
	 * trusted on the way back out).
	 */

	use BigTree\Services\FourOhFourService;
	use BigTree\Services\UserService;

	function parity_validity_admin(): array {
		$id = parity_seed_user(["level" => 1]);

		return [$id, (object)["id" => $id, "level" => 1, "permissions" => []]];
	}

	function test_parity_ai_update_user_rejects_invalid_timezone() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		[$admin_id, $admin] = parity_validity_admin();
		$target_id = parity_seed_user(["level" => 0, "timezone" => "UTC"]);

		try {
			$bad = $svc->aiValidateUserUpdate([
				"user_id" => $target_id,
				"timezone" => "Mars/Olympus_Mons",
			], $admin);

			T::ok(isset($bad["error"]), "an invalid timezone is refused on update, as it is on create");
			T::ok(strpos($bad["error"], "timezone") !== false, "the error explains what's wrong");

			$good = $svc->aiValidateUserUpdate([
				"user_id" => $target_id,
				"timezone" => "Europe/London",
			], $admin);

			T::ok(!empty($good["ok"]), "a valid IANA identifier validates");
			T::equals($good["payload"]["changes"]["timezone"], "Europe/London", "the change is staged");

			// The payload is stored for up to 24h; a guard made at staging is never
			// assumed to still hold at approval.
			$approval = $svc->aiUpdateUser([
				"user_id" => $target_id,
				"changes" => ["timezone" => "Mars/Olympus_Mons"],
			], $admin);

			T::equals($approval["mode"], "error", "an invalid timezone in a stored payload is refused at approval");
			T::equals(
				(string)SQL::fetchSingle("SELECT timezone FROM bigtree_users WHERE id = ?", $target_id),
				"UTC",
				"the live row was not touched"
			);
		} finally {
			parity_delete_users($admin_id, $target_id);
		}
	}

	function test_parity_ai_user_name_is_required_and_cannot_be_blanked() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		[$admin_id, $admin] = parity_validity_admin();
		$target_id = parity_seed_user(["level" => 0, "name" => "ZZ Named Fixture", "company" => "ZZ Co"]);
		$email = "zz-validity-" . bin2hex(random_bytes(4)) . "@example.com";

		try {
			// POST /users declares name required; the AI create path never checked it,
			// so an AI-made account could be the only one with no name at all.
			$nameless = $svc->aiValidateUserCreate(["email" => $email], $admin);
			T::ok(isset($nameless["error"]), "creating a user with no name is refused");
			T::ok(strpos($nameless["error"], "name") !== false, "the error names the missing field");

			$named = $svc->aiValidateUserCreate(["email" => $email, "name" => "ZZ Validity"], $admin);
			T::ok(!empty($named["ok"]), "supplying a name validates");

			$blank_approval = $svc->aiCreateUser(["email" => $email, "name" => ""], $admin);
			T::equals($blank_approval["mode"], "error", "a nameless stored payload is refused at approval");

			$blanking = $svc->aiValidateUserUpdate(["user_id" => $target_id, "name" => "  "], $admin);
			T::ok(isset($blanking["error"]), "blanking an existing user's name is refused");

			$blanking_approval = $svc->aiUpdateUser([
				"user_id" => $target_id,
				"changes" => ["name" => ""],
			], $admin);

			T::equals($blanking_approval["mode"], "error", "a blanking payload is refused at approval too");
			T::equals(
				(string)SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $target_id),
				"ZZ Named Fixture",
				"the live name survived"
			);

			// company is genuinely optional and still clearable.
			$clear_company = $svc->aiValidateUserUpdate(["user_id" => $target_id, "company" => ""], $admin);
			T::ok(!empty($clear_company["ok"]), "clearing an optional field still works");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE email = ?", $email);
			parity_delete_users($admin_id, $target_id);
		}
	}

	function test_parity_ai_create_redirect_refuses_loops_and_junk_destinations() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new FourOhFourService();
		[$admin_id, $admin] = parity_validity_admin();
		$source = "zz-validity-" . bin2hex(random_bytes(3));

		try {
			// A bare phrase would be stored and served verbatim as a 301 Location.
			$junk = $svc->aiValidateRedirectCreate(["from" => "/{$source}", "to" => "pricing page"], $admin);
			T::ok(isset($junk["error"]), "a destination that isn't a URL or a path is refused");
			T::ok(strpos($junk["error"], "/pricing") !== false, "the error shows the expected shape");

			$hostless = $svc->aiValidateRedirectCreate(["from" => "/{$source}", "to" => "https://"], $admin);
			T::ok(isset($hostless["error"]), "an absolute URL with no domain is refused");

			$loop = $svc->aiValidateRedirectCreate(["from" => "/{$source}", "to" => "/{$source}"], $admin);
			T::ok(isset($loop["error"]), "a redirect pointing at itself is refused");
			T::ok(strpos($loop["error"], "loop") !== false, "the error explains it would loop");

			// The same target written as a full URL normalizes to the same path.
			$loop_absolute = $svc->aiValidateRedirectCreate([
				"from" => "/{$source}",
				"to" => rtrim(WWW_ROOT, "/") . "/{$source}",
			], $admin);
			T::ok(isset($loop_absolute["error"]), "a self-redirect written as a full URL is caught too");

			$ok = $svc->aiValidateRedirectCreate(["from" => "/{$source}", "to" => "/{$source}-new"], $admin);
			T::ok(!empty($ok["ok"]), "a distinct site-relative destination validates");
			T::ok(isset($ok["preview"]["warning"]), "an internal destination with no page behind it warns");

			$external = $svc->aiValidateRedirectCreate([
				"from" => "/{$source}",
				"to" => "https://example.com/pricing",
			], $admin);
			T::ok(!empty($external["ok"]), "an external destination validates");
			T::ok(!isset($external["preview"]["warning"]), "an external destination isn't checked against pages");

			// Never trusted on the way back out of the proposal store.
			$approval = $svc->aiCreateRedirect([
				"from" => "/{$source}",
				"to" => "/{$source}",
				"site_key" => null,
			], $admin);

			T::equals($approval["mode"], "error", "a looping stored payload is refused at approval");
			T::ok(
				!SQL::fetchSingle("SELECT id FROM bigtree_404s WHERE broken_url = ?", $source),
				"nothing was written"
			);
		} finally {
			SQL::query("DELETE FROM bigtree_404s WHERE broken_url LIKE ?", $source . "%");
			parity_delete_users($admin_id);
		}
	}
