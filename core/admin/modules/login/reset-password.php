<?
	$user = $admin->getUserByHash(end($bigtree["path"]));
	$failure = false;
	
	if ($_POST["password"]) {
		if (!$admin->validatePassword($_POST["password"])) {
			$failure = "validation";
		} elseif ($_POST["password"] != $_POST["confirm_password"]) {
			$failure = "match";
		} else {
			$admin->changePassword(end($bigtree["path"]),$_POST["password"]);
		}
	}

	$policy = array_filter((array)$bigtree["security-policy"]["password"]) ? $bigtree["security-policy"]["password"] : false;
	if ($policy) {
		$policy_text = "<p>Requirements</p><ul>";
		if ($policy["length"]) {
			$policy_text .= "<li>Minimum length &mdash; ".$policy["length"]." characters</li>";
		}
		if ($policy["mixedcase"]) {
			$policy_text .= "<li>Both upper and lowercase letters</li>";
		}
		if ($policy["numbers"]) {
			$policy_text .= "<li>At least one number</li>";
		}
		if ($policy["nonalphanumeric"]) {
			$policy_text .= "<li>At least one special character (i.e. $%*)</li>";
		}
		$policy_text .= "</ul>";
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2>Reset Your Password</h2>
		<?
			if ($failure) {
		?>
		<p class="error_message clear">
			<? if ($failure == "match") { ?>
			Passwords did not match. Please try again.
			<? } else { ?>
			Password did not meet requirements.
			<? } ?>
		</p>
		<?
			}
			if (!$user) {
		?>
		<fieldset class="clear">
			<p>This reset request has expired. <a href="<?=$login_root?>forgot-password/">Click Here</a> to request a new link.</p>
		</fieldset>
		<br />
		<?
			} else {
		?>
		<fieldset>
			<label>New Password</label>
			<input class="text<? if ($policy) { ?> has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?><? } ?>" type="password" name="password" />
			<? if ($policy) { ?>
			<p class="password_policy">Password Policy In Effect</p>
			<? } ?>
		</fieldset>
		<fieldset>
			<label>Confirm New Password</label>
			<input class="text" type="password" name="confirm_password" />
		</fieldset>
		<fieldset class="lower">
			<input type="submit" class="button blue" value="Reset" />
		</fieldset>
		<?
			}
		?>
	</form>
</div>
<script>
	$("input[type=password]").each(function() {
		BigTreePasswordInput(this);
	});
</script>