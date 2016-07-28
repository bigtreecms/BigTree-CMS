<?php
	namespace BigTree;
	
	$id = intval($_POST["id"]);

	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/edit/".$id."/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p><?=Text::translate('To update a user, please access the <a href=":link:">Edit user</a> page.', false, array(":link:" => ADMIN_ROOT."users/edit/$id/"))?></p>
	</section>
</div>
<?php
	} else {
		Globalize::POST();

		// Check security policy
		if ($password && !$admin->validatePassword($password)) {
			$_SESSION["bigtree_admin"]["update_user"] = $_POST;
			$_SESSION["bigtree_admin"]["update_user"]["error"] = "password";
			Utils::growl("Users","Invalid Password","error");
			Router::redirect(ADMIN_ROOT."users/edit/$id/");
		}

		// Check permission level
		$error = false;
		$user = new User($id);

		// Don't let a user edit someone that has higher access levels than they do
		if ($user->Level > Auth::user()->Level) {
			$error = "level";
		}

		// Don't let a user change their own level
		if ($id == Auth::user()->ID) {
			$level = Auth::user()->Level;
		}

		if ($error === false) {
			$permission_data = json_decode($permissions,true);
			$permissions = array(
				"page" => $permission_data["Page"],
				"module" => $permission_data["Module"],
				"resources" => $permission_data["Resource"],
				"module_gbp" => $permission_data["ModuleGBP"]
			);

			$alerts = json_decode($alerts,true);
			
			if (!$user->update($email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest)) {
				$error = "email";
			}
		}

		if ($error !== false) {
			$_SESSION["bigtree_admin"]["update_user"] = $_POST;
			$_SESSION["bigtree_admin"]["update_user"]["error"] = $error;
			Utils::growl("Users","Update Failed","error");

			Router::redirect(ADMIN_ROOT."users/edit/$id/");
		}
		
		Utils::growl("Users","Updated User");
		
		Router::redirect(ADMIN_ROOT."users/");
	}
