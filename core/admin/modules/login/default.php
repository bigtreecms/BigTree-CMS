<?php
	namespace BigTree;
	
	/**
	 * @global string $login_root
	 */
	
	if (isset($_GET["error"])) {
		$failure = true;
		$user = Text::htmlEncode($_SESSION["bigtree_admin"]["email"]);
	} else {
		$user = "";
		$failure = false;
	}
?>
<form method="post" action="<?=ADMIN_ROOT?>login/process/" class="module">
	<?php
		if (!empty($bigtree["ban_expiration"])) {
	?>
	<p class="error_message clear"><?=Text::translate("You are temporarily banned due to failed login attempts.<br />You may try logging in again after :ban_expiration:.", false, array(":ban_expiration:" => Auth::$BanExpiration))?></p>
	<?php
			if ($bigtree["ban_is_user"]) {
	?>
	<fieldset>
		<p><?=Text::translate('You may <a href=":reset_link:">reset your password</a> to remove your ban.', false, array(":reset_link:" => $login_root."forgot-password/"))?></p>
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
		<label for="user"><?=Text::translate("Email")?></label>
		<input type="email" id="user" name="user" class="text" value="<?=$user?>" />
	</fieldset>
	<fieldset>
		<label for="password"><?=Text::translate("Password")?></label>
		<input type="password" id="password" name="password" class="text" />
		<?php
			if ($bigtree["security-policy"]["remember_disabled"] != "on") {
		?>
		<label class="visually_hidden" for="login_field_stay_logged_in"><?=Text::translate("Remember Me")?></label>
		<p><input id="login_field_stay_logged_in" type="checkbox" name="stay_logged_in" checked="checked" /> <?=Text::translate("Remember Me")?></p>
		<?php
			}
		?>
	</fieldset>
	<fieldset class="lower">
		<a href="<?=$login_root?>forgot-password/" class="forgot_password"><?=Text::translate("Forgot Password?")?></a>
		<input type="submit" class="button blue" value="<?=Text::translate("Login", true)?>" />
	</fieldset>
	<?php
		}
	?>
</form>