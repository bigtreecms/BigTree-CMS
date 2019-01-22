<?php
	if ($bigtree["security-policy"]["remember_disabled"]) {
		$stay_logged_in = false;
	} else {
		$stay_logged_in = $_POST["stay_logged_in"] ? true : false;
	}

	if ($bigtree["security-policy"]["two_factor"] == "google") {
		$token = $admin->verifyLogin2FA($_POST["user"], $_POST["password"]);

		if (is_null($token)) {
			$_SESSION["bigtree_admin"]["email"] = $_POST["user"];
			BigTree::redirect(ADMIN_ROOT."login/?error");
		} else {
			$_SESSION["bigtree_admin"]["2fa_domain"] = $_POST["domain"];
			$_SESSION["bigtree_admin"]["2fa_stay_logged_in"] = $stay_logged_in;

			if ($token) {
				BigTree::redirect(ADMIN_ROOT."login/2fa/");
			} else {
				BigTree::redirect(ADMIN_ROOT."login/2fa/setup/");
			}
		}
	} else {
		if (!$admin->login($_POST["user"], $_POST["password"], $stay_logged_in, $_POST["domain"])) {
			$_SESSION["bigtree_admin"]["failed_login_email"] = $_POST["user"];
			BigTree::redirect(ADMIN_ROOT."login/?error");
		}
	}
