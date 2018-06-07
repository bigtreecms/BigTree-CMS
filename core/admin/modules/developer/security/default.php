<?php
	$security_policy = $cms->getSetting("bigtree-internal-security-policy");
	BigTree::globalizeArray($security_policy,"htmlspecialchars");

	if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
		$session_handler = "db";
	} else {
		$session_handler = "default";
	}
?>
<div class="container" id="security_settings">
	<form method="post" action="<?=DEVELOPER_ROOT?>security/update/">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<div class="contain">
				<div class="left">
					<h3>Failed Logins</h3>
					<p>Rules to prevent brute forcing of passwords.</p>
					<fieldset class="rule">
						<input type="text" name="user_fails[count]" value="<?=$user_fails["count"]?>" /> failed logins for a given <strong>user</strong> over <br />
						<input type="text" name="user_fails[time]" value="<?=$user_fails["time"]?>" /> minutes leads to a ban of the <strong>user</strong> for <br />
						<input type="text" name="user_fails[ban]" value="<?=$user_fails["ban"]?>" /> <strong>minutes</strong> or until a password reset
					</fieldset>
					<fieldset class="rule">
						<input type="text" name="ip_fails[count]" value="<?=$ip_fails["count"]?>" /> failed logins for a given <strong>IP address</strong> over <br />
						<input type="text" name="ip_fails[time]" value="<?=$ip_fails["time"]?>" /> minutes leads to a ban of the <strong>IP address</strong> for <br />
						<input type="text" name="ip_fails[ban]" value="<?=$ip_fails["ban"]?>" /> <strong>hours</strong>
					</fieldset>
					<br />
					<h3>Passwords</h3>
					<fieldset>
						<input type="checkbox" id="security_settings_field_send_invites" name="password[invitations]"<?php if ($password["invitations"]) { ?> checked<?php } ?>>
						<label class="for_checkbox" for="security_settings_field_send_invites">Send Invitations for Users to Set Initial Password</label>
					</fieldset>
					<fieldset>
						<input type="checkbox" id="security_settings_field_mixed_case" name="password[mixedcase]"<?php if ($password["mixedcase"]) { ?> checked="checked"<?php } ?> />
						<label for="security_settings_field_mixed_case" class="for_checkbox">Require Mixed-Case <small>(both lowercase and uppercase characters)</small></label>
					</fieldset>
					<fieldset>
						<input id="security_settings_field_require_numbers" type="checkbox" name="password[numbers]"<?php if ($password["numbers"]) { ?> checked="checked"<?php } ?> />
						<label for="security_settings_field_require_numbers" class="for_checkbox">Require Numbers</label>
					</fieldset>
					<fieldset>
						<input id="security_settings_field_nonalphanumeric" type="checkbox" name="password[nonalphanumeric]"<?php if ($password["nonalphanumeric"]) { ?> checked="checked"<?php } ?> />
						<label for="security_settings_field_nonalphanumeric" class="for_checkbox">Require Non-Alphanumeric Characters <small>(i.e. $ # ^ *)</small></label>
					</fieldset>
					<fieldset>
						<label for="security_settings_field_password_length">Minimum Password Length <small>(leave blank or 0 to have no restriction)</small></label>
						<input id="security_settings_field_password_length" type="text" name="password[length]" value="<?=$password["length"]?>" />
					</fieldset>
					<br />
					<h3>Login Options</h3>
					<fieldset>
						<input id="security_settings_field_google_authenticator" type="checkbox" name="two_factor" value="google"<?php if ($two_factor == "google") { ?> checked<?php } ?>>
						<label for="security_settings_field_google_authenticator" class="for_checkbox">Enable Two-Factor Authentication via Google Authenticator</label>
					</fieldset>
					<fieldset>
						<input id="security_settings_field_disable_remember" type="checkbox" name="remember_disabled" value="on"<?php if ($remember_disabled == "on") { ?> checked<?php } ?>>
						<label for="security_settings_field_disable_remember" class="for_checkbox">Disable "Remember Me" Function</label>
					</fieldset>
					<?php
						if ($session_handler == "db") {
					?>
					<fieldset>
						<input id="security_settings_field_logout_all" type="checkbox" name="logout_all" value="on"<?php if ($logout_all == "on") { ?> checked<?php } ?>>
						<label for="security_settings_field_logout_all" class="for_checkbox">Logout All Users Sessions When User Clicks Logout</label>
					</fieldset>
					<?php
						}
					?>
				</div>
				<div class="right">
					<fieldset>
						<h3>Allowed IP Ranges</h3>
						<p>Enter IP address ranges below to restrict login access.<br />Each line should contain two IP addresses separated by a comma that delineate the beginning and end of the IP ranges.</p>
						<textarea name="allowed_ips" placeholder="i.e. 192.168.1.1, 192.168.1.128"><?=$allowed_ips?></textarea>
					</fieldset>
					<fieldset>
						<h3>Permanent Ban List</h3>
						<p>Include a list of IP addresses you wish to permanently ban from logging into the admin area (one per line).</p>
						<textarea name="banned_ips"><?=$banned_ips?></textarea>
					</fieldset>

					<?php
						if ($session_handler == "db") {
					?>
					<a class="button red" href="<?=DEVELOPER_ROOT?>security/logout-all/">Logout All Users</a>
					<?php
						}
					?>
				</div>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update Security Settings" />
		</footer>
	</form>
</div>