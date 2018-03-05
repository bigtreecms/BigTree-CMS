<?php
	namespace BigTree;
	
	include Router::getIncludePath("inc/lib/GoogleAuthenticator.php");
	
	$site = new Page(0);
	$secret = Auth\GoogleAuthenticator::generateSecret();
	$qr = Auth\GoogleAuthenticator::getQRCode($site->NavigationTitle, $secret);
	
	define("ADMIN_BODY_CLASS", "two_factor_body_setup");
?>
<form class="two_factor_setup_form" action="<?=ADMIN_ROOT?>login/2fa/setup-process/" method="post">
	<input type="hidden" name="secret" value="<?=$secret?>" />
	
	<img src="<?=$qr?>" alt="" class="two_factor_code" />
	<div class="two_factor_instructions">
		<h3>Instructions</h3>
		<ul>
			<li>Download the Google Authenticator App (<a href="https://itunes.apple.com/us/app/google-authenticator/id388497605" target="_blank">iOS</a>, <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</a>)</li>
			<li>Scan the QR code to the right.</li>
			<li>Enter the code shown in the app in the field below.</li>
		</ul>
	</div>
	
	<br class="clear" />
	<hr />
	<br>
	
	<p class="error_message" style="display: none;">The code you entered expired or was incorrect.</p>
	
	<fieldset>
		<label for="2fa_field_auth_code">Authenticator Code</label>
		<input id="2fa_field_auth_code" type="number" name="code" class="text" />
	</fieldset>
	<fieldset class="lower">
		<input type="submit" class="button blue" value="Complete Setup" />
	</fieldset>
</form>

<script>
	$(".two_factor_setup_form").on("submit", function(ev) {
		ev.preventDefault();
		ev.stopPropagation();
		$(".error_message").hide();

		$.ajax("<?=ADMIN_ROOT?>ajax/two-factor-check/", { method: "POST", data: $(this).serialize() }).done(function(response) {
			if (response === "true") {
				$(".two_factor_setup_form").off("submit").submit();
			} else {
				$(".error_message").show();
			}
		});

	});
</script>