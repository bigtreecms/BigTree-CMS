<?php
	namespace BigTree;
	
	Auth::remove2FASecret($_GET["user"]);
	Admin::growl("Users", "Removed Two Factor Authentication");
	Router::redirect(ADMIN_ROOT."users/edit/".htmlspecialchars($_GET["user"])."/");
	
