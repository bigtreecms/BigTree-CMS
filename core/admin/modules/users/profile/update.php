<?php
	namespace BigTree;
	
	if ($_SERVER["HTTP_REFERER"] != ADMIN_ROOT."users/profile/") {
?>
<div class="container">
	<section>
		<p>To update your profile, please access your  <a href="<?=ADMIN_ROOT?>users/profile/">Profile</a> page directly.</p>
	</section>
</div>
<?php
	} else {
		if ($_POST["password"] && !$admin->validatePassword($_POST["password"])) {
			$_SESSION["bigtree_admin"]["update_profile"] = $_POST;
			
			$admin->growl("Users","Invalid Password","error");
			Router::redirect(ADMIN_ROOT."users/profile/");	
		}
		$admin->updateProfile($_POST["name"],$_POST["company"],$_POST["daily_digest"],$_POST["password"]);

		$admin->growl("Users","Updated Profile");
		Router::redirect(ADMIN_ROOT."dashboard/");
	}
?>