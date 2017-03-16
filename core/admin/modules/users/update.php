<?
	$id = intval($_POST["id"]);

	$admin->verifyCSRFToken();
?>
<div class="container">
	<section>
		<p>To update a user, please access the <a href="<?=ADMIN_ROOT?>users/edit/<?=$id?>/">Edit User</a> page.</p>
	</section>
</div>
<?
	// Check security policy
	if ($_POST["password"] && !$admin->validatePassword($_POST["password"])) {
		$_SESSION["bigtree_admin"]["update_user"] = $_POST;
		$_SESSION["bigtree_admin"]["update_user"]["error"] = "password";
		$admin->growl("Users","Invalid Password","error");
		BigTree::redirect(ADMIN_ROOT."users/edit/$id/");
	}

	$perms = json_decode($_POST["permissions"],true);
	$_POST["permissions"] = array("page" => $perms["Page"],"module" => $perms["Module"],"resources" => $perms["Resource"],"module_gbp" => $perms["ModuleGBP"]);
	$_POST["alerts"] = json_decode($_POST["alerts"],true);
	$success = $admin->updateUser($id,$_POST);
	
	if (!$success) {
		$_SESSION["bigtree_admin"]["update_user"] = $_POST;
		$_SESSION["bigtree_admin"]["update_user"]["error"] = "email";
		$admin->growl("Users","Update Failed","error");
		BigTree::redirect(ADMIN_ROOT."users/edit/$id/");
	}
	
	$admin->growl("Users","Updated User");
	
	BigTree::redirect(ADMIN_ROOT."users/");
?>