<?php
	if (isset($_GET["error"])) {
		$failure = true;
		$user = htmlspecialchars($_SESSION["bigtree_admin"]["email"]);
	} else {
		$user = "";
		$failure = false;
	}
?>
<form method="post" action="<?=ADMIN_ROOT?>login/process/" class="module">
	<?php
		if ($bigtree["ban_expiration"]) {
	?>
	<p class="error_message clear">You are temporarily banned due to failed login attempts.<br />You may try logging in again after <?=$bigtree["ban_expiration"]?>.</p>
	<?php
			if ($bigtree["ban_is_user"]) {
	?>
	<fieldset>
		<p>You may <a href="<?=$login_root?>forgot-password/">reset your password</a> to remove your ban.</p>
	</fieldset>
	<br />
	<?php
			}
		} else {
			if ($failure) {
	?>
	<p class="error_message clear">You've entered an invalid email address and/or password.</p>
	<?php
			}

			if (!empty($_REQUEST["domain"])) {
	?>
	<input type="hidden" name="domain" value="<?=BigTree::safeEncode($_REQUEST["domain"])?>" />
	<?php
			}
	?>
	<fieldset>
		<label>Email</label>
		<input type="email" id="user" name="user" class="text" value="<?=$user?>" />
	</fieldset>
	<fieldset>
		<label>Password</label>
		<input type="password" id="password" name="password" class="text" />
		<?php
			if ($bigtree["security-policy"]["remember_disabled"] != "on") {
		?>
		<p><input type="checkbox" name="stay_logged_in" checked="checked" /> Remember Me</p>
		<?php
			}
		?>
	</fieldset>
	<fieldset class="lower">
		<a href="<?=$login_root?>forgot-password/" class="forgot_password">Forgot Password?</a>
		<input type="submit" class="button blue" value="Login" />
	</fieldset>
	<?php
		}
	?>
</form>