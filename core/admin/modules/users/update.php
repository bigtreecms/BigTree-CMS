<?php
	namespace BigTree;
	
	$id = intval($_POST["id"]);

	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/edit/".$id."/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p>To update a user, please access the <a href="<?=ADMIN_ROOT?>users/edit/<?=$id?>/">Edit User</a> page.</p>
	</section>
</div>
<?php
	} else {
		// Check security policy
		if ($_POST["password"] && !$admin->validatePassword($_POST["password"])) {
			$_SESSION["bigtree_admin"]["update_user"] = $_POST;
			$_SESSION["bigtree_admin"]["update_user"]["error"] = "password";
			$admin->growl("Users","Invalid Password","error");
			Router::redirect(ADMIN_ROOT."users/edit/$id/");
		}

		// Check permission level
		$error = false;
		$user = User::get($id);

		if ($user["level"] <= $admin->Level) {
			$error = "level";
		} elseif ($id == $admin->ID && intval($level) != $admin->Level) {
			$error = "level";
		}

		if (!$error) {
			$permission_data = json_decode($_POST["permissions"],true);
			$permissions = $_POST["permissions"] = array(
				"page" => $permission_data["Page"],
				"module" => $perms["Module"],
				"resources" => $perms["Resource"],
				"module_gbp" => $perms["ModuleGBP"]
			);
			$alerts = $_POST["alerts"] = json_decode($_POST["alerts"],true);
			
			if (!User::update($id,$email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest) {
				$error = "email";
			}
		}
		
		if ($error) {
			$_SESSION["bigtree_admin"]["update_user"] = $_POST;
			$_SESSION["bigtree_admin"]["update_user"]["error"] = $error;
			$admin->growl("Users","Update Failed","error");
			Router::redirect(ADMIN_ROOT."users/edit/$id/");
		}
		
		$admin->growl("Users","Updated User");
		
		Router::redirect(ADMIN_ROOT."users/");
	}
?>