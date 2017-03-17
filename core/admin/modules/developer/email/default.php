<?
	$email_service = new BigTreeEmailService;

	$services = array(
		"local" => "Local Server",
		"mandrill" => "Mandrill",
		"mailgun" => "Mailgun",
		"postmark" => "Postmark",
		"sendgrid" => "SendGrid"
	);
?>
<div class="container">
	<header>
		<nav class="left">
			<? foreach ($services as $key => $val) { ?>
			<a href="#<?=$key?>_tab"<? if ($key == $email_service->Service) { ?> class="active"<? } ?>><?=$val?></a>
			<? } ?>
		</nav>
	</header>
	<section id="local_tab"<? if ($email_service->Service != "local") { ?> style="display: none;"<? } ?>>
		<p>Mail delivery over your local server uses <a href="http://php.net/manual/en/mail.configuration.php" target="_blank">PHP's native mail settings</a> for email delivery. This may increase your risk of having emails marked as spam.</p>
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<? $admin->drawCSRFToken() ?>
			<input type="hidden" name="service" value="local" />
			<fieldset>
				<label>BigTree "From" Address <small>(for sending Daily Digest and Forgot Password emails)</small></label>
				<input type="text" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" />
			</fieldset>
		</form>
	</section>
	<section id="mandrill_tab"<? if ($email_service->Service != "mandrill") { ?> style="display: none;"<? } ?>>
		<p><a href="http://www.mandrill.com/" target="_blank">Mandrill</a> is a transactional email API by the makers of <a href="http://www.mailchimp.com/" target="_blank">MailChimp</a>.<br />Your API Key can be found on the Settings page of the Mandrill control panel.</p>
		<p>It is advised that you verify your "sending domain" (the domain that you plan to use in the "From" address of your emails) via DKIM and SPF to reduce the risk of your email being marked as spam.</p>
		<hr />
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<? $admin->drawCSRFToken() ?>
			<input type="hidden" name="service" value="mandrill" />
			<fieldset>
				<label>API Key</label>
				<input type="text" name="mandrill_key" value="<?=htmlspecialchars($email_service->Settings["mandrill_key"])?>" />
			</fieldset>
			<fieldset>
				<label>BigTree "From" Address <small>(required for sending Daily Digest and Forgot Password emails)</small></label>
				<input type="text" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" />
			</fieldset>
		</form>
	</section>
	<section id="mailgun_tab"<? if ($email_service->Service != "mailgun") { ?> style="display: none;"<? } ?>>
		<p><a href="http://www.mailgun.com/" target="_blank">Mailgun</a> is a transactional email API by <a href="http://www.rackspace.com/" target="_blank">Rackspace</a>.<br />You must enter both your API Key (found on the landing page after logging in) and the domain you added to Mailgun that you plan to send emails from (you may use your Mailgun sandbox subdomain to send test emails).</p>
		<p>It is <strong>required</strong> that you verify your "sending domain" (the domain that you plan to use in the "From" address of your emails) via DKIM and SPF to send more than 300 emails per day. It is also recommended even if you fall below that threshold as it will reduce the risk of your email being marked as spam.</p>
		<hr />
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<? $admin->drawCSRFToken() ?>
			<input type="hidden" name="service" value="mailgun" />
			<fieldset>
				<label>API Key</label>
				<input type="text" name="mailgun_key" value="<?=htmlspecialchars($email_service->Settings["mailgun_key"])?>" />
			</fieldset>
			<fieldset>
				<label>Domain <small>(i.e. sandbox42162361dg235125512.mailgun.org</small></label>
				<input type="text" name="mailgun_domain" value="<?=htmlspecialchars($email_service->Settings["mailgun_domain"])?>" />
			</fieldset>
			<fieldset>
				<label>BigTree "From" Address <small>(required for sending Daily Digest and Forgot Password emails)</small></label>
				<input type="text" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" />
			</fieldset>
		</form>
	</section>
	<section id="postmark_tab"<? if ($email_service->Service != "postmark") { ?> style="display: none;"<? } ?>>
		<p><a href="http://www.postmarkapp.com/" target="_blank">Postmark</a> is a transactional email API by the makers of <a href="http://www.beanstalkapp.com/" target="_blank">Beanstalk</a>.<br />You can find your API Key on the Credentials page of the Postmark server you wish to use.</p>
		<p>It is advised that you verify your "sending domain" (the domain that you plan to use in the "From" address of your emails) via DKIM and SPF to reduce the risk of your email being marked as spam.</p>
		<hr />
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<? $admin->drawCSRFToken() ?>
			<input type="hidden" name="service" value="postmark" />
			<fieldset>
				<label>API Key</label>
				<input type="text" name="postmark_key" value="<?=htmlspecialchars($email_service->Settings["postmark_key"])?>" />
			</fieldset>
			<fieldset>
				<label>BigTree "From" Address <small>(required for sending Daily Digest and Forgot Password emails)</small></label>
				<input type="text" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" />
			</fieldset>
		</form>
	</section>
	<section id="sendgrid_tab"<? if ($email_service->Service != "sendgrid") { ?> style="display: none;"<? } ?>>
		<p><a href="https://www.sendgrid.com/" target="_blank">SendGrid</a> is a transactional email delivery and management service.<br />You must enter both your API user and API key (the password that you use to log into sendgrid.com).</p>
		<hr />
		<form method="post" action="<?=DEVELOPER_ROOT?>email/update/">
			<? $admin->drawCSRFToken() ?>
			<input type="hidden" name="service" value="sendgrid" />
			<fieldset>
				<label>API User <small>(same as SMTP username)</small></label>
				<input type="text" name="sendgrid_api_user" value="<?=htmlspecialchars($email_service->Settings["sendgrid_api_user"])?>" />
			</fieldset>
			<fieldset>
				<label>API Key <small>(same as SMTP password)</small></small></label>
				<input type="text" name="sendgrid_api_key" value="<?=htmlspecialchars($email_service->Settings["sendgrid_api_key"])?>" />
			</fieldset>
			<fieldset>
				<label>BigTree "From" Address <small>(required for sending Daily Digest and Forgot Password emails)</small></label>
				<input type="text" name="bigtree_from" value="<?=htmlspecialchars($email_service->Settings["bigtree_from"])?>" />
			</fieldset>
		</form>
	</section>
	<footer>
		<a class="button submit blue" href="#">Set Email Delivery Service</a>
		<p>The selected service will be used when BigTree sends out "Daily Digest" and "Forgot Password" emails as well as when your code calls BigTreeEmailService's "sendEmail" method.</p>
	</footer>
</div>
<script>
	BigTreeFormNavBar.init();
	$(".container .submit").click(function(ev) {
		ev.preventDefault();
		$(".container section:visible form").submit();
	});
</script>
