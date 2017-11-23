<?php
	if ($admin->login2FA($_POST["code"])) {
		BigTree::redirect(ADMIN_ROOT."login/2fa/?error");
	}
