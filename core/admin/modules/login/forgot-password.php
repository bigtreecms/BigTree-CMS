<?
	$failure = false;

	if ($_POST["email"]) {
		if (!$admin->forgotPassword($_POST["email"])) {
			$failure = true;
		}
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2>Forgot Your Password?</h2>
		<? if ($failure) { ?><p class="error_message clear">You've entered an invalid email address.</p><? } ?>
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