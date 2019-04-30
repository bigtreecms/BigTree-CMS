<?php
	namespace BigTree;

	$email_service = new Email;

	$services = [
		"Local" => Text::translate("Local Server"),
		"SMTP" => Text::translate("SMTP"),
		"Mandrill" => Text::translate("Mandrill"),
		"Mailgun" => Text::translate("Mailgun"),
		"Postmark" => Text::translate("Postmark"),
		"SendGrid" => Text::translate("SendGrid")
	];
?>
<div class="container">
	<header>
		<nav class="left">
			<?php foreach ($services as $key => $val) { ?>
			<a href="#<?=$key?>_tab"<?php if ($key == $email_service->Service) { ?> class="active"<?php } ?>><?=$val?></a>
			<?php } ?>
		</nav>
	</header>

	<section id="Local_tab"<?php if ($email_service->Service != "Local") { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('Mail delivery over your local server uses <a href="http://php.net/manual/en/mail.configuration.php" target="_blank">PHP\'s native mail settings</a> for email delivery. This may increase your risk of having emails marked as spam.')?></p>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="service" value="Local">

			<fieldset>
				<label for="local_field_bigtree_from"><?=Text::translate('Default "From" Email Address')?></label>
				<input id="local_field_bigtree_from" type="email" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>">
			</fieldset>
			
			<fieldset>
				<label for="local_field_bigtree_from_name"><?=Text::translate('Default "From" Name')?></label>
				<input id="local_field_bigtree_from_name" type="text" name="bigtree_from_name" value="<?=htmlspecialchars($email_service->Settings["bigtree_from_name"])?>">
			</fieldset>
		</form>
	</section>

	<section id="SMTP_tab"<?php if ($email_service->Service != "SMTP") { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('Mail delivery over a standard SMTP server uses <a href="https://github.com/PHPMailer/PHPMailer" target="_blank">PHPMailer</a> over SMTP for email delivery.')?></p>
		<hr>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="service" value="SMTP">
			
			<fieldset>
				<label for="smtp_field_host"><?=Text::translate("Hostname")?></label>
				<input id="smtp_field_host" type="text" name="smtp_host" value="<?=htmlspecialchars($email_service->Settings["smtp_host"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="smtp_field_port"><?=Text::translate("Port <small>(default is 25)")?></small></label>
				<input id="smtp_field_port" type="text" name="smtp_port" value="<?=htmlspecialchars($email_service->Settings["smtp_port"])?>">
			</fieldset>
			
			<fieldset>
				<label for="smtp_field_user"><?=Text::translate("Username")?></label>
				<input id="smtp_field_user" type="text" name="smtp_user" value="<?=htmlspecialchars($email_service->Settings["smtp_user"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="smtp_field_password"><?=Text::translate("Password")?></label>
				<input id="smtp_field_password" type="password" name="smtp_password" value="<?=htmlspecialchars($email_service->Settings["smtp_password"])?>" required>
			</fieldset>
			
			<fieldset>
				<label><?=Text::translate("Security")?></label>
				<select name="smtp_security">
					<option value=""><?=Text::translate("Plain Text")?></option>
					<option value="ssl"<?php if ($email_service->Settings["smtp_security"] == "ssl") { ?> selected="selected"<?php } ?>><?=Text::translate("SSL")?></option>
					<option value="tls"<?php if ($email_service->Settings["smtp_security"] == "tls") { ?> selected="selected"<?php } ?>><?=Text::translate("TLS")?></option>
				</select>
			</fieldset>
			
			<fieldset>
				<label for="smtp_field_bigtree_from"><?=Text::translate('Default "From" Address')?></label>
				<input id="smtp_field_bigtree_from" type="email" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>">
			</fieldset>

			<fieldset>
				<label for="smtp_field_bigtree_from_name"><?=Text::translate('Default "From" Name')?></label>
				<input id="smtp_field_bigtree_from_name" type="text" name="bigtree_from_name" value="<?=htmlspecialchars($email_service->Settings["bigtree_from_name"])?>">
			</fieldset>
		</form>
	</section>

	<section id="Mandrill_tab"<?php if ($email_service->Service != "Mandrill") { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('<a href="http://www.mandrill.com/" target="_blank">Mandrill</a> is a transactional email API by the makers of <a href="http://www.mailchimp.com/" target="_blank">MailChimp</a>.<br>Your API Key can be found on the Settings page of the Mandrill control panel.')?></p>
		<p><?=Text::translate('It is advised that you verify your "sending domain" (the domain that you plan to use in the "From" address of your emails) via DKIM and SPF to reduce the risk of your email being marked as spam.')?></p>
		<hr>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="service" value="Mandrill">
			
			<fieldset>
				<label for="mandrill_field_key"><?=Text::translate("API Key")?></label>
				<input id="mandrill_field_key" type="text" name="mandrill_key" value="<?=htmlspecialchars($email_service->Settings["mandrill_key"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="mandrill_field_bigtree_from"><?=Text::translate('Default "From" Address')?></label>
				<input id="mandrill_field_bigtree_from" type="email" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" required>
			</fieldset>

			<fieldset>
				<label for="mandrill_field_bigtree_from_name"><?=Text::translate('Default "From" Name')?></label>
				<input id="mandrill_field_bigtree_from_name" type="text" name="bigtree_from_name" value="<?=htmlspecialchars($email_service->Settings["bigtree_from_name"])?>">
			</fieldset>
		</form>
	</section>

	<section id="Mailgun_tab"<?php if ($email_service->Service != "Mailgun") { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('<a href="http://www.mailgun.com/" target="_blank">Mailgun</a> is a transactional email API by <a href="http://www.rackspace.com/" target="_blank">Rackspace</a>.<br>You must enter both your API Key (found on the landing page after logging in) and the domain you added to Mailgun that you plan to send emails from (you may use your Mailgun sandbox subdomain to send test emails).')?></p>
		<p><?=Text::translate('It is <strong>required</strong> that you verify your "sending domain" (the domain that you plan to use in the "From" address of your emails) via DKIM and SPF to send more than 300 emails per day. It is also recommended even if you fall below that threshold as it will reduce the risk of your email being marked as spam.')?></p>
		<hr>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="service" value="Mailgun">
			
			<fieldset>
				<label for="mailgun_field_key"><?=Text::translate("API Key")?></label>
				<input id="mailgun_field_key" type="text" name="mailgun_key" value="<?=htmlspecialchars($email_service->Settings["mailgun_key"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="mailgun_field_domain"><?=Text::translate('Domain <small>(i.e. sandbox42162361dg235125512.mailgun.org</small>')?></label>
				<input id="mailgun_field_domain" type="text" name="mailgun_domain" value="<?=htmlspecialchars($email_service->Settings["mailgun_domain"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="mailgun_field_bigtree_from"><?=Text::translate('Default "From" Address')?></label>
				<input id="mailgun_field_bigtree_from" type="email" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" required>
			</fieldset>

			<fieldset>
				<label for="mailgun_field_bigtree_from_name"><?=Text::translate('Default "From" Name')?></label>
				<input id="mailgun_field_bigtree_from_name" type="text" name="bigtree_from_name" value="<?=htmlspecialchars($email_service->Settings["bigtree_from_name"])?>">
			</fieldset>
		</form>
	</section>

	<section id="Postmark_tab"<?php if ($email_service->Service != "Postmark") { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('<a href="http://www.postmarkapp.com/" target="_blank">Postmark</a> is a transactional email API by the makers of <a href="http://www.beanstalkapp.com/" target="_blank">Beanstalk</a>.<br>You can find your API Key on the Credentials page of the Postmark server you wish to use.')?></p>
		<p><?=Text::translate('It is advised that you verify your "sending domain" (the domain that you plan to use in the "From" address of your emails) via DKIM and SPF to reduce the risk of your email being marked as spam.')?></p>
		<hr>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="service" value="Postmark">

			<fieldset>
				<label for="postmark_field_key"><?=Text::translate("API Key")?></label>
				<input id="postmark_field_key" type="text" name="postmark_key" value="<?=htmlspecialchars($email_service->Settings["postmark_key"])?>" required>
			</fieldset>

			<fieldset>
				<label for="postmark_field_bigtree_from"><?=Text::translate('Default "From" Address')?></label>
				<input id="postmark_field_bigtree_from" type="email" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" required>
			</fieldset>

			<fieldset>
				<label for="postmark_field_bigtree_from_name"><?=Text::translate('Default "From" Name')?></label>
				<input id="postmark_field_bigtree_from_name" type="text" name="bigtree_from_name" value="<?=htmlspecialchars($email_service->Settings["bigtree_from_name"])?>">
			</fieldset>
		</form>
	</section>

	<section id="SendGrid_tab"<?php if ($email_service->Service != "SendGrid") { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('<a href="https://www.sendgrid.com/" target="_blank">SendGrid</a> is a transactional email delivery and management service.<br>You must enter both your API user and API key (the password that you use to log into sendgrid.com).')?></p>
		<hr>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="service" value="SendGrid">
			
			<fieldset>
				<label for="sendgrid_field_user"><?=Text::translate('API User <small>(same as SMTP username)</small>')?></label>
				<input id="sendgrid_field_user" type="text" name="sendgrid_api_user" value="<?=htmlspecialchars($email_service->Settings["sendgrid_api_user"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="sendgrid_field_key"><?=Text::translate('API Key <small>(same as SMTP password)</small>')?></label>
				<input id="sendgrid_field_key" type="text" name="sendgrid_api_key" value="<?=htmlspecialchars($email_service->Settings["sendgrid_api_key"])?>" required>
			</fieldset>
			
			<fieldset>
				<label for="sendgrid_field_bigtree_from"><?=Text::translate('Default "From" Address')?></label>
				<input id="sendgrid_field_bigtree_from" type="email" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" required>
			</fieldset>

			<fieldset>
				<label for="sendgrid_field_bigtree_from_name"><?=Text::translate('Default "From" Name')?></label>
				<input id="sendgrid_field_bigtree_from_name" type="text" name="bigtree_from_name" value="<?=htmlspecialchars($email_service->Settings["bigtree_from_name"])?>">
			</fieldset>
		</form>
	</section>

	<footer>
		<a class="button submit blue" href="#"><?=Text::translate("Set Email Delivery Service")?></a>
		<p><?=Text::translate('The selected service will be used when BigTree sends out "Daily Digest" and "Forgot Password" emails as well as when your code calls BigTreeEmailService\'s "sendEmail" method or the BigTree\\Email Class.')?></p>
	</footer>
</div>

<script>
	BigTreeFormNavBar.init();
	$(".container .submit").click(function(ev) {
		ev.preventDefault();
		$(".container section:visible form").submit();
	});
</script>