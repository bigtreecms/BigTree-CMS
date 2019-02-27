<?php
	namespace BigTree;

	$security_policy = Setting::value("bigtree-internal-security-policy");
?>
<div class="container" id="security_settings">
	<form method="post" action="<?=DEVELOPER_ROOT?>security/update/">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="contain">
				<div class="left">
					<h3><?=Text::translate("Failed Logins")?></h3>
					<p><?=Text::translate("Rules to prevent brute forcing of passwords.")?></p>
					
					<fieldset class="rule">
						<input type="text" name="user_fails[count]" value="<?=Text::htmlEncode($security_policy["user_fails"]["count"])?>" /> <?=Text::translate("failed logins for a given <strong>user</strong> over")?> <br />
						<input type="text" name="user_fails[time]" value="<?=Text::htmlEncode($security_policy["user_fails"]["time"])?>" /> <?=Text::translate("minutes leads to a ban of the <strong>user</strong> for")?> <br />
						<input type="text" name="user_fails[ban]" value="<?=Text::htmlEncode($security_policy["user_fails"]["ban"])?>" /> <?=Text::translate("<strong>minutes</strong> or until a password reset")?>
					</fieldset>

					<fieldset class="rule">
						<input type="text" name="ip_fails[count]" value="<?=Text::htmlEncode($security_policy["ip_fails"]["count"])?>" /> <?=Text::translate("failed logins for a given <strong>IP address</strong> over")?> <br />
						<input type="text" name="ip_fails[time]" value="<?=Text::htmlEncode($security_policy["ip_fails"]["time"])?>" /> <?=Text::translate("minutes leads to a ban of the <strong>IP address</strong> for")?> <br />
						<input type="text" name="ip_fails[ban]" value="<?=Text::htmlEncode($security_policy["ip_fails"]["ban"])?>" /> <strong><?=Text::translate("hours")?></strong>
					</fieldset>
					
					<br />
					
					<h3><?=Text::translate("Passwords")?></h3>
					
					<fieldset>
						<input type="checkbox" id="security_settings_field_send_invites" name="password[invitations]"<?php if (!empty($security_policy["password"]["invitations"])) { ?> checked<?php } ?>>
						<label class="for_checkbox" for="security_settings_field_send_invites"><?=Text::translate("Send Invitations for Users to Set Initial Password")?></label>
					</fieldset>

					<fieldset>
						<label for="security_field_password_length"><?=Text::translate('Minimum Password Length <small>(leave blank or 0 to have no restriction)</small>')?></label>
						<input id="security_field_password_length" type="text" name="password[length]" value="<?=Text::htmlEncode($security_policy["password"]["length"])?>" />
					</fieldset>

					<fieldset>
						<input id="security_field_mixed_case" type="checkbox" name="password[mixedcase]"<?php if ($security_policy["password"]["mixedcase"]) { ?> checked="checked"<?php } ?> />
						<label for="security_field_mixed_case" class="for_checkbox"><?=Text::translate('Require Mixed-Case <small>(both lowercase and uppercase characters)</small>')?></label>
					</fieldset>
					
					<fieldset>
						<input id="security_field_numbers" type="checkbox" name="password[numbers]"<?php if ($security_policy["password"]["numbers"]) { ?> checked="checked"<?php } ?> />
						<label for="security_field_numbers" class="for_checkbox"><?=Text::translate("Require Numbers")?></label>
					</fieldset>
					
					<fieldset>
						<input id="security_field_nonalphanumeric" type="checkbox" name="password[nonalphanumeric]"<?php if ($security_policy["password"]["nonalphanumeric"]) { ?> checked="checked"<?php } ?> />
						<label for="security_field_nonalphanumeric" class="for_checkbox"><?=Text::translate('Require Non-Alphanumeric Characters <small>(i.e. $ # ^ *)</small>')?></label>
					</fieldset>

					<br />
					<h3>Login Options</h3>

					<fieldset>
						<input id="security_field_two_factor_google" type="checkbox" name="two_factor" value="google"<?php if ($security_policy["two_factor"] == "google") { ?> checked<?php } ?>>
						<label for="security_field_two_factor_google" class="for_checkbox"><?=Text::translate("Enable Two-Factor Authentication via Google Authenticator")?></label>
					</fieldset>
					
					<fieldset>
						<input id="security_field_remember_disabled" type="checkbox" name="remember_disabled" value="on"<?php if ($security_policy["remember_disabled"] == "on") { ?> checked<?php } ?>>
						<label for="security_field_remember_disabled" class="for_checkbox"><?=Text::translate('Disable "Remember Me" Function')?></label>
					</fieldset>
				</div>
				<div class="right">
					<fieldset>
						<h3><?=Text::translate("Allowed IP Ranges")?></h3>
						<p><?=Text::translate('Enter IP address ranges below to restrict login access.<br />Each line should contain two IP addresses separated by a comma that delineate the beginning and end of the IP ranges.')?></p>
						<textarea name="allowed_ips" placeholder="i.e. 192.168.1.1, 192.168.1.128"><?=Text::htmlEncode($security_policy["allowed_ips"])?></textarea>
					</fieldset>
					
					<fieldset>
						<h3><?=Text::translate("Permanent Ban List")?></h3>
						<p><?=Text::translate("Include a list of IP addresses you wish to permanently ban from logging into the admin area (one per line).")?></p>
						<textarea name="banned_ips"><?=Text::htmlEncode($security_policy["banned_ips"])?></textarea>
					</fieldset>
				</div>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update Security Settings", true)?>" />
		</footer>
	</form>
</div>