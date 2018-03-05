<?php
	namespace BigTree;
	
	if (Auth::login2FA($_POST["code"])) {
		Router::redirect(ADMIN_ROOT."login/2fa/?error");
	}
