<?
	if ($_SERVER["HTTP_REFERER"] != ADMIN_ROOT."users/profile/") {
?>
<div class="container">
	<section>
		<p>To update your profile, please access your  <a href="<?=ADMIN_ROOT?>users/profile/">Profile</a> page directly.</p>
	</section>
</div>
<?
	} else {
		$admin->updateProfile($_POST);
		$admin->growl("Users","Updated Profile");
		BigTree::redirect(ADMIN_ROOT."dashboard/");
	}
?>