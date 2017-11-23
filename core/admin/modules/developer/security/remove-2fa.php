<?php
	$admin->remove2FASecret($_GET["user"]);
	$admin->growl("Users", "Removed Two Factor Authentication");

	BigTree::redirect(ADMIN_ROOT."users/edit/".htmlspecialchars($_GET["user"])."/");
	