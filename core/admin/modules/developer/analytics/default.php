<?php
	$analytics = new BigTreeGoogleAnalytics4;
?>
<div class="container">
	<?php
		if (!empty($analytics->Settings["verified"])) {
	?>
	<section>
		<p>Google Analytics information is being fed into BigTree.</p>
		<hr />
		<p>
			<strong>Service Account:</strong> <?=$analytics->Settings["credentials"]["client_email"]?><br>
			<strong>Property ID:</strong> <?=$analytics->Settings["property_id"]?>
		</p>
	</section>
	<footer>
		<a class="button red" href="<?=DEVELOPER_ROOT?>analytics/disconnect/">Disconnect</a>
	</footer>
	<?php
		} else {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>analytics/upload-client-file/" class="module" enctype="multipart/form-data">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p>To activate the Google Analytics Data API (GA4):</p>
			<hr />
			<ol>
				<li>Go to the <a href="https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries">API Quickstart</a> page and download your client configuration file.</li>
				<li>Upload your client configuration file below to continue.</li>
			</ol>
			<hr />
			<fieldset class="developer_cloud_key">
				<label>Client Configuration File</label>
				<input type="file" name="client_file" accept="application/json" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Google Cloud Storage" />
		</footer>
	</form>
	<?php
		}
	?>
</div>