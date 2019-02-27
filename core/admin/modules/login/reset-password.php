<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global string $login_root
	 */
	
	$user = User::getByHash(end($bigtree["path"]));
	$failure = false;
	
	if ($_POST["password"]) {
		if (!User::validatePassword($_POST["password"])) {
			$failure = "validation";
		} elseif ($_POST["password"] != $_POST["confirm_password"]) {
			$failure = "match";
		} else {
			$user = User::getByHash(end($bigtree["path"]));
			
			if ($user) {
				$user->ChangePasswordHash = "";
				$user->Password = $_POST["password"];
				
				$user->save();
				$user->removeBans();

				Router::redirect(($bigtree["config"]["force_secure_login"] ? str_replace("http://","https://",ADMIN_ROOT) : ADMIN_ROOT)."login/reset-success/");
			}
		}
	}

	$policy = array_filter((array)$bigtree["security-policy"]["password"]) ? $bigtree["security-policy"]["password"] : false;
	
	if ($policy) {
		$policy_text = "<p>".Text::translate("Requirements")."</p><ul>";
		
		if ($policy["length"]) {
			$policy_text .= "<li>".Text::translate("Minimum length &mdash; :length: characters", false, array(":length:" => $policy["length"]))."</li>";
		}
		
		if ($policy["mixedcase"]) {
			$policy_text .= "<li>".Text::translate("Both upper and lowercase letters")."</li>";
		}
		
		if ($policy["numbers"]) {
			$policy_text .= "<li>".Text::translate("At least one number")."</li>";
		}
		
		if ($policy["nonalphanumeric"]) {
			$policy_text .= "<li>".Text::translate("At least one special character (i.e. $%*)")."</li>";
		}
		
		$policy_text .= "</ul>";
	} else {
		$policy_text = "";
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2><?=Text::translate(isset($_GET["welcome"]) ? "Set Your Password" : "Reset Your Password"))?></h2>
		<?php
			if ($failure) {
		?>
		<p class="error_message clear">
			<?=Text::translate(($failure == "match") ? "Passwords did not match. Please try again." : "Password did not meet requirements.")?>
		</p>
		<?php
			}
			
			if (!$user) {
		?>
		<fieldset class="clear">
			<p><?=Text::translate("This reset request has expired.")?>" <a href="<?=$login_root?>forgot-password/"><?=Text::translate("Click Here")?></a> <?=Text::translate("to request a new link.")?></p>
		</fieldset>
		<br />
		<?php
			} else {
		?>
		<fieldset>
			<label for="password_field_password"><?=Text::translate("New Password")?></label>
			<input id="password_field_password" class="text<?php if ($policy) { ?> has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?><?php } ?>" type="password" name="password" />
			<?php if ($policy) { ?>
			<p class="password_policy"><?=Text::translate("Password Policy In Effect")?></p>
			<?php } ?>
		</fieldset>
		<fieldset>
			<label for="password_field_confirm"><?=Text::translate("Confirm New Password")?></label>
			<input id="password_field_confirm" class="text" type="password" name="confirm_password" />
		</fieldset>
		<fieldset class="lower">
			<input type="submit" class="button blue" value="<?=Text::translate("Reset", true)?>" />
		</fieldset>
		<?php
			}
		?>
	</form>
</div>
<script>
	$("input[type=password]").each(function() {
		BigTreePasswordInput(this);
	});
</script>