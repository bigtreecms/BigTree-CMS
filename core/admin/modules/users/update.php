<?
	if ($_SERVER["HTTP_REFERER"] != ADMIN_ROOT."users/edit/".$_POST["id"]."/") {
?>
<div class="container">
	<section>
		<p>To update a user, please access the <a href="<?=ADMIN_ROOT?>users/edit/<?=$_POST["id"]?>/">Edit User</a> page.</p>
	</section>
</div>
<?
	} else {
		$perms = json_decode($_POST["permissions"],true);
		$_POST["permissions"] = array("page" => $perms["Page"],"module" => $perms["Module"],"resources" => $perms["Resource"],"module_gbp" => $perms["ModuleGBP"]);
		$_POST["alerts"] = json_decode($_POST["alerts"],true);
		$success = $admin->updateUser($_POST["id"],$_POST);
		
		if (!$success) {
			$_SESSION["bigtree_admin"]["update_user"] = $_POST;
			$admin->growl("Users","Update Failed","error");
			BigTree::redirect(ADMIN_ROOT."users/edit/".end($bigtree["path"])."/");
		}
		
		$admin->growl("Users","Updated User");
		
		BigTree::redirect(ADMIN_ROOT."users/");
	}
?>