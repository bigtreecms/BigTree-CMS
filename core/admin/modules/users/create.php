<?
	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/add/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p>To create a user, please access the <a href="<?=ADMIN_ROOT?>users/add/">Add User</a> page.</p>
	</section>
</div>
<?
	} else {
		// Check security policy
		if (!$admin->validatePassword($_POST["password"])) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$_SESSION["bigtree_admin"]["create_user"]["error"] = "password";
			$admin->growl("Users","Invalid Password","error");
			BigTree::redirect(ADMIN_ROOT."users/add/");
		}

		$id = $admin->createUser($_POST);	
		if (!$id) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$_SESSION["bigtree_admin"]["create_user"]["error"] = "email";
			$admin->growl("Users","Creation Failed","error");
			BigTree::redirect(ADMIN_ROOT."users/add/");
		}
	
		$admin->growl("Users","Added User");
		BigTree::redirect(ADMIN_ROOT."users/edit/$id/");
	}
?>