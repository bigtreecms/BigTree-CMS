<?
	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/profile/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p>To update your profile, please access your  <a href="<?=ADMIN_ROOT?>users/profile/">Profile</a> page directly.</p>
	</section>
</div>
<?
	} else {
		if ($_POST["password"] && !$admin->validatePassword($_POST["password"])) {
			$_SESSION["bigtree_admin"]["update_profile"] = $_POST;
			$admin->growl("Users","Invalid Password","error");
			BigTree::redirect(ADMIN_ROOT."users/profile/");	
		}
		$admin->updateProfile($_POST);
		$admin->growl("Users","Updated Profile");
		BigTree::redirect(ADMIN_ROOT."dashboard/");
	}
?>