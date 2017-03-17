<?
	$security_policy = $cms->getSetting("bigtree-internal-security-policy");
	BigTree::globalizeArray($security_policy,"htmlspecialchars");
?>
<div class="container" id="security_settings">
	<form method="post" action="<?=DEVELOPER_ROOT?>security/update/">
		<? $admin->drawCSRFToken() ?>
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
					<h3>Password Strength</h3>
					<fieldset>
						<label>Minimum Password Length <small>(leave blank or 0 to have no restriction)</small></label>
						<input type="text" name="password[length]" value="<?=$password["length"]?>" />
					</fieldset>
					<fieldset>
						<input type="checkbox" name="password[mixedcase]"<? if ($password["mixedcase"]) { ?> checked="checked"<? } ?> />
						<label class="for_checkbox">Require Mixed-Case <small>(both lowercase and uppercase characters)</small></label>
					</fieldset>
					<fieldset>
						<input type="checkbox" name="password[numbers]"<? if ($password["numbers"]) { ?> checked="checked"<? } ?> />
						<label class="for_checkbox">Require Numbers</label>
					</fieldset>
					<fieldset>
						<input type="checkbox" name="password[nonalphanumeric]"<? if ($password["nonalphanumeric"]) { ?> checked="checked"<? } ?> />
						<label class="for_checkbox">Require Non-Alphanumeric Characters <small>(i.e. $ # ^ *)</small></label>
					</fieldset>
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
				</div>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update Security Settings" />
		</footer>
	</form>
</div>