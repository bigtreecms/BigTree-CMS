<?php
	namespace BigTree;
	
	/**
	 * @global array $policy
	 */
	
	CSRF::verify();
	
	// Check security policy
	if (empty($policy["invitations"]) && !User::validatePassword($_POST["password"])) {
		$_SESSION["bigtree_admin"]["create_user"] = $_POST;
		$_SESSION["bigtree_admin"]["create_user"]["error"] = "password";
		
		Admin::growl("Users", "Invalid Password", "error");
		Router::redirect(ADMIN_ROOT."users/add/");
	}
	
	// Don't let them exceed permission level
	if (Auth::user()->Level < intval($_POST["level"])) {
		$_POST["level"] = Auth::user()->Level;
	}
	
	$user = User::create($_POST["email"], $_POST["password"], $_POST["name"], $_POST["company"], $_POST["level"],
						 $_POST["permissions"], $_POST["alerts"], $_POST["daily_digest"]);
	
	if ($user === false) {
		$_SESSION["bigtree_admin"]["create_user"] = $_POST;
		$_SESSION["bigtree_admin"]["create_user"]["error"] = "email";
		
		Admin::growl("Users", "Creation Failed", "error");
		Router::redirect(ADMIN_ROOT."users/add/");
	}
	
	Admin::growl("Users", "Added User");
	Router::redirect(ADMIN_ROOT."users/edit/".$user->ID."/");
	