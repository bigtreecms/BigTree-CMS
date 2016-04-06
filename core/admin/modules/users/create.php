<?php
	namespace BigTree;
	
	if ($_SERVER["HTTP_REFERER"] != ADMIN_ROOT."users/add/") {
?>
<div class="container">
	<section>
		<p>To create a user, please access the <a href="<?=ADMIN_ROOT?>users/add/">Add User</a> page.</p>
	</section>
</div>
<?php
	} else {
		BigTree::globalizePOSTVars();

		// Check security policy
		if (!$admin->validatePassword($password)) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$_SESSION["bigtree_admin"]["create_user"]["error"] = "password";
			$admin->growl("Users","Invalid Password","error");
			Router::redirect(ADMIN_ROOT."users/add/");
		}

		// Don't let them exceed permission level
		if ($admin->Level < intval($level)) {
			$level = $admin->Level;
		}

		$id = BigTree\User::create($email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest);
			
		if (!$id) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$_SESSION["bigtree_admin"]["create_user"]["error"] = "email";
			$admin->growl("Users","Creation Failed","error");
			Router::redirect(ADMIN_ROOT."users/add/");
		}
	
		$admin->growl("Users","Added User");
		Router::redirect(ADMIN_ROOT."users/edit/$id/");
	}
?>