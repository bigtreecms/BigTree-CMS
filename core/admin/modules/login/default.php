<?php
	$failure = false;
	if (isset($_POST['user']) && isset($_POST['password'])) {
	    if (!$admin->login($_POST['user'], $_POST['password'], $_POST['stay_logged_in'])) {
	        $failure = true;
	    }
	}

	$user = isset($_POST['user']) ? htmlspecialchars($_POST['user']) : '';
?>
<form method="post" action="" class="module">
	<?php
		if ($bigtree['ban_expiration']) {
		    ?>
	<p class="error_message clear">You are temporarily banned due to failed login attempts.<br />You may try logging in again after <?=$bigtree['ban_expiration']?>.</p>
	<?php
			if ($bigtree['ban_is_user']) {
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
		    ?>
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
	<?php

		}
	?>
</form>