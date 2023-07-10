<?php
	$analytics = new BigTreeGoogleAnalytics4;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>analytics/set-property-id/">
		<section>
			<p>
				Next you need to add the following user to your Google Analytics account with <strong><em>Viewer</em></strong> permissions in order to grant BigTree access to your data:
				<br><br>
				<strong><?=$analytics->Settings["credentials"]["client_email"]?></strong>
			</p>

			<hr>

			<p>
				Please be aware that it may take Google several minutes after you create your credentials file for this email address to appear as a valid user to invite in Google Analytics.
				Once you have added the user, enter your GA4 Property ID below and click Continue below to test the integration.
			</p>

			<hr>
			
			<fieldset>
				<label>GA4 Property ID</label>
				<input type="text" name="property_id" value="<?=$analytics->Settings["property_id"] ?? ""?>" required />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
