<?php
	$failure = false;

	if ($_POST["email"]) {
		if (!$admin->forgotPassword($_POST["email"])) {
			$failure = true;
		}
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2><?=Text::translate("Forgot Your Password?")?></h2>
		<?php if ($failure) { ?><p class="error_message clear"><?=Text::translate("You've entered an invalid email address.")?></p><?php } ?>
		<fieldset>
			<label><?=Text::translate("Email")?></label>
			<input class="text" type="email" name="email" />
		</fieldset>
		<fieldset class="lower">
			<a href="<?=$login_root?>" class="forgot_password"><?=Text::translate("Back to Login")?></a>
			<input type="submit" class="button retrieve_password blue" value="<?=Text::translate("Submit", true)?>" />
		</fieldset>
	</form>
</div>