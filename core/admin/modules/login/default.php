<?php
	namespace BigTree;
	
	$failure = false;
	if (isset($_POST["user"]) && isset($_POST["password"])) {
		if (!$admin->login($_POST["user"],$_POST["password"],$_POST["stay_logged_in"])) {
			$failure = true;
		} else {
			if (isset($_SESSION["bigtree_login_redirect"])) {
				Router::redirect($_SESSION["bigtree_login_redirect"]);
			} else {
				Router::redirect(ADMIN_ROOT);
			}
		}
	}
	
	$user = isset($_POST["user"]) ? htmlspecialchars($_POST["user"]) : "";
?>
<form method="post" action="" class="module">
	<?php
		if (!empty($bigtree["ban_expiration"])) {
	?>
	<p class="error_message clear"><?=Text::translate("You are temporarily banned due to failed login attempts.<br />You may try logging in again after")?> <?=$bigtree["ban_expiration"]?>.</p>
	<?php
			if ($bigtree["ban_is_user"]) {
	?>
	<fieldset>
		<p><?=Text::translate("You may")?> <a href="<?=$login_root?>forgot-password/"><?=Text::translate("reset your password")?></a> <?=Text::translate("to remove your ban")?>.</p>
	</fieldset>
	<br />
	<?php
			}
		} else {
			if ($failure) {
	?>
	<p class="error_message clear"><?=Text::translate("You've entered an invalid email address and/or password.")?></p>
	<?php
			}
	?>
	<fieldset>
		<label><?=Text::translate("Email")?></label>
		<input type="email" id="user" name="user" class="text" value="<?=$user?>" />
	</fieldset>
	<fieldset>
		<label><?=Text::translate("Password")?></label>
		<input type="password" id="password" name="password" class="text" />
		<p><input type="checkbox" name="stay_logged_in" checked="checked" /> <?=Text::translate("Remember Me")?></p>
	</fieldset>
	<fieldset class="lower">
		<a href="<?=$login_root?>forgot-password/" class="forgot_password"><?=Text::translate("Forgot Password?")?></a>
		<input type="submit" class="button blue" value="<?=Text::translate("Login", true)?>" />
	</fieldset>
	<?php
		}
	?>
</form>