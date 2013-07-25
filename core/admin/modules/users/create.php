<?
	if ($_SERVER["HTTP_REFERER"] != ADMIN_ROOT."users/add/") {
?>
<div class="container">
	<section>
		<p>To create a user, please access the <a href="<?=ADMIN_ROOT?>users/add/">Add User</a> page.</p>
	</section>
</div>
<?
	} else {
		$admin->requireLevel(1);
		$id = $admin->createUser($_POST);	
		
		if (!$id) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$admin->growl("Users","Creation Failed","error");
			BigTree::redirect(ADMIN_ROOT."users/add/");
		}
	
		$admin->growl("Users","Added User");
		BigTree::redirect(ADMIN_ROOT."users/edit/$id/");
	}
?>