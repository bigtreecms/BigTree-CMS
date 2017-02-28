<?
	$failure = false;

	if ($_POST["email"]) {
		$admin->forgotPassword($_POST["email"]);
		BigTree::redirect(ADMIN_ROOT."login/forgot-success/");
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2>Forgot Your Password?</h2>
		<fieldset>
			<label>Email</label>
			<input class="text" type="email" name="email" />
		</fieldset>
		<fieldset class="lower">
			<a href="<?=$login_root?>" class="forgot_password">Back to Login</a>
			<input type="submit" class="button retrieve_password blue" value="Submit" />
		</fieldset>
	</form>
</div>