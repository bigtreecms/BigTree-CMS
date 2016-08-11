<?php
	namespace BigTree;
	
	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/profile/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p><?=Text::translate('To update your profile, please access your <a href=":profile_link:">Profile</a> page directly.', false, array(":profile_link:" => ADMIN_ROOT."users/profile/"))?></p>
	</section>
</div>
<?php
	} else {
		if ($_POST["password"] && !User::validatePassword($_POST["password"])) {
			$_SESSION["bigtree_admin"]["update_profile"] = $_POST;
			
			Utils::growl("Users","Invalid Password","error");
			Router::redirect(ADMIN_ROOT."users/profile/");	
		}
		
		
		User::updateProfile($_POST["name"], $_POST["company"], $_POST["daily_digest"], $_POST["password"]);

		Utils::growl("Users","Updated Profile");
		Router::redirect(ADMIN_ROOT."dashboard/");
	}
?>