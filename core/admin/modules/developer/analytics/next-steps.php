<?php
	$analytics = new BigTreeGoogleAnalytics4;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>analytics/set-property-id/">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p>
				Next you need to add the following service account to your Google Analytics 4 property with <strong><em>Viewer</em></strong> permissions:
				<br><br>
				<strong><?=htmlspecialchars($analytics->Settings["credentials"]["client_email"] ?? "")?></strong>
			</p>

			<hr>

			<p>
				In <a href="https://analytics.google.com/" target="_blank">Google Analytics</a>, open Admin, then Property access management. Click Add users, paste the email address above, assign the Viewer role, and uncheck "Notify new users by email". Click Add.
			</p>

			<p>
				It may take Google several minutes after you create the key file for this email address to appear as a valid user. If Google Analytics says the email does not match a Google Account, wait a few minutes and try again.
			</p>

			<p>
				Once the user has been added, enter your numeric GA4 Property ID below (shown in the property selector and on Admin / Property details, not the Measurement ID that starts with G-) and click Continue to test the integration.
			</p>

			<hr>
			
			<fieldset>
				<label>GA4 Property ID <small>(numeric, not the G- Measurement ID)</small></label>
				<input type="text" name="property_id" value="<?=htmlspecialchars($analytics->Settings["property_id"] ?? "")?>" required />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
