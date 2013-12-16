<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/google/activate/" class="module" enctype="multipart/form-data">
		<section>
			<p>To activate Google Cloud Storage you must follow these steps:</p>
			<hr />
			<ol>
				<li>Login to the Google Cloud Console and create a project.</li>
				<li>Click into the project and enter the "API &amp; auth" section. Enable access to the Google Cloud Storage JSON API.</li>
				<li>Click into the "Registered apps" section and create an application using the "Web Application" type.</li>
				<li>Expaned the "Oauth 2.0 Client" block and enter the Client ID and Client Secret from that block below.</li>
				<li>Enter <?=DEVELOPER_ROOT?>cloud-storage/google/return/ as the "Redirect URI" in the OAuth pane and click the button below to save it.</li>
				<li>If you want to use the Temporary Private URLs feature of Cloud Storage (for providing URLs that expire after a certain amount of time), expaned the "Certificate" block and click the "Generate" button. Then click the "Download private key" button to save your private key. Upload that private key below and enter the email address from the Certificate block as the Certificate Email Address.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Google Cloud Storage account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Project ID</label>
				<input type="text" name="project" value="<?=htmlspecialchars($cloud->Settings["project"])?>" />
			</fieldset>
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" value="<?=htmlspecialchars($cloud->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($cloud->Settings["secret"])?>" />
			</fieldset>
			<fieldset>
				<label>Certificate Email Address <small>(optional, needed only for Temporary Private URLs)</small></label>
				<input type="text" name="certificate_email" value="<?=htmlspecialchars($cloud->Settings["certificate_email"])?>" />
			</fieldset>
			<fieldset class="developer_cloud_key">
				<label>Certificate Private Key <small>(optional, needed only for Temporary Private URLs)</small></label>
				<input type="file" name="private_key" />
				<? if ($cloud->Settings["private_key"]) { ?>
				<span class="icon_approve icon_approve_on"></span>
				<? } ?>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Google Cloud Storage" />
		</footer>
	</form>
</div>