<?
	$failure = false;
	if (isset($_POST["user"]) && isset($_POST["password"])) {
		if (!$admin->login($_POST["user"],$_POST["password"],$_POST["stay_logged_in"])) {
			$failure = true;
		}
	}
	
	$user = isset($_POST["user"]) ? htmlspecialchars($_POST["user"]) : "";
?>
<form method="post" action="" class="module">
	<? if ($failure) { ?><p class="error_message clear">You've entered an invalid email address and/or password.</p><? } ?>
	<fieldset>
		<label>Email</label>
		<input type="email" id="user" name="user" class="text" value="<?=$user?>" />
	</fieldset>
	<fieldset>
		<label>Password</label>
		<input type="password" id="password" name="password" class="text" />

		<p><input type="checkbox" name="stay_logged_in" checked="checked" /> Remember Me</p>
	</fieldset>
	<fieldset class="lower">
		<a href="<?=$login_root?>forgot-password/" class="forgot_password">Forgot Password?</a>
		<input type="submit" class="button blue" value="Login" />
	</fieldset>
</form>