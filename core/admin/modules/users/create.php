<?php
	namespace BigTree;
	
	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/add/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p><?=Text::translate("To create a user, please access the")?> <a href="<?=ADMIN_ROOT?>users/add/"><?=Text::translate("Add User")?></a> <?=Text::translate("page")?>.</p>
	</section>
</div>
<?php
	} else {
		\BigTree::globalizePOSTVars();

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

		$id = User::create($email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest);
			
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