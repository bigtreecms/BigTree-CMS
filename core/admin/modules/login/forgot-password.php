<?php
	namespace BigTree;
	
	/**
	 * @global string $login_root
	 */
	
	$failure = false;

	if ($_POST["email"]) {
		$user = User::getByEmail($_POST["email"]);
		
		if ($user) {
			$user->initPasswordReset();
		}
		
		Router::redirect($login_root."forgot-success/");
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2><?=Text::translate("Forgot Your Password?")?></h2>
		<fieldset>
			<label for="forgot_field_email"><?=Text::translate("Email")?></label>
			<input id="forgot_field_email" class="text" type="email" name="email" />
		</fieldset>
		<fieldset class="lower">
			<a href="<?=$login_root?>" class="forgot_password"><?=Text::translate("Back to Login")?></a>
			<input type="submit" class="button retrieve_password blue" value="<?=Text::translate("Submit", true)?>" />
		</fieldset>
	</form>
</div>