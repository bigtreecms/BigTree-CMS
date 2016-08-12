<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Google $google
	 */
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/google/activate/" class="module" enctype="multipart/form-data">
		<section>
			<p><?=Text::translate("To activate Google Cloud Storage you must follow these steps:")?></p>
			<hr />
			<p class="notice_message"><?=Text::translate("Google's Developer Console changes frequently, these steps may not be up to date.")?></p>
			<ol>
				<li><?=Text::translate('Login to the <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a> and create a project. Enter the Project ID below.')?></li>
				<li><?=Text::translate('Click into the project and enter the "APIs &amp; auth" / APIs section. Enable access to the Google Cloud Storage and Google Cloud Storage JSON API.')?></li>
				<li><?=Text::translate('Click into the "Credentials" section and click the "Create New Client ID" button.')?></li>
				<li><?=Text::translate('Enter :redirect_uri: as an "Authorized Redirect URI" and choose "Web Application" for the Application Type.', false, array(":redirect_uri:" => DEVELOPER_ROOT."cloud-storage/google/return/"))?></li>
				<li><?=Text::translate('Enter the Client ID and Client Secret that was created from the previous step below.')?></li>
				<li><?=Text::translate('If you want to use the Temporary Private URLs feature of Cloud Storage (for providing URLs that expire after a certain amount of time), click the "Create New Client ID" button again, this time choosing "Service Account" as the type. Your private key will automatically download. Upload that private key below and enter the email address from the Service Account block as the Certificate Email Address.')?></li>
				<li><?=Text::translate('If you have not yet signed up for Cloud Storage, go into Storage / Cloud Storage / Storage browser and sign up for the Cloud Storage product.')?></li>
				<li><?=Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Google Cloud Storage account.')?></li>
			</ol>
			<hr />
			<fieldset>
				<label for="google_field_project"><?=Text::translate("Project ID")?></label>
				<input id="google_field_project" type="text" name="project" value="<?=Text::htmlEncode($google->Project)?>" />
			</fieldset>
			<fieldset>
				<label for="google_field_client"><?=Text::translate("Client ID")?></label>
				<input id="google_field_client" type="text" name="key" value="<?=Text::htmlEncode($google->Key)?>" />
			</fieldset>
			<fieldset>
				<label for="google_field_secret"><?=Text::translate("Client Secret")?></label>
				<input id="google_field_secret" type="text" name="secret" value="<?=Text::htmlEncode($google->Secret)?>" />
			</fieldset>
			<fieldset>
				<label for="google_field_certificate_email"><?=Text::translate('Certificate Email Address <small>(optional, needed only for Temporary Private URLs)</small>')?></label>
				<input id="google_field_certificate_email" type="text" name="certificate_email" value="<?=Text::htmlEncode($google->CertificateEmail)?>" />
			</fieldset>
			<fieldset class="developer_cloud_key">
				<label for="google_field_certificate_key"><?=Text::translate('Certificate Private Key <small>(optional, needed only for Temporary Private URLs)</small>')?></label>
				<input id="google_field_certificate_key" type="file" name="private_key" />
				<?php if ($google->PrivateKey) { ?>
				<span class="icon_approve icon_approve_on"></span>
				<?php } ?>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Activate Google Cloud Storage", true)?>" />
		</footer>
	</form>
</div>