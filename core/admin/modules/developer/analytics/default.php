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
			<strong>Service Account:</strong> <?=htmlspecialchars($analytics->Settings["credentials"]["client_email"])?><br>
			<strong>Property ID:</strong> <?=htmlspecialchars($analytics->Settings["property_id"])?>
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
			<p>To connect BigTree to the Google Analytics Data API (GA4):</p>
			<hr />
			<ol>
				<li>Sign in to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create a project (or select an existing one).</li>
				<li>Enable the <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank">Google Analytics Data API</a> for that project.</li>
				<li>Open <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">IAM &amp; Admin / Service Accounts</a>.</li>
				<li>Choose the project you created.</li>
				<li>Click "Create Service Account". Enter a name (for example, "bigtree-analytics") and click "Done". You can skip the optional permission steps.</li>
				<li>Open the service account, go to the "Keys" tab, click "Add Key" / "Create new key", choose JSON, and click "Create". A JSON key file will download to your computer.</li>
				<li>Upload that JSON key file below to continue.</li>
			</ol>
			<hr />
			<fieldset class="developer_cloud_key">
				<label>Service Account JSON Key</label>
				<input type="file" name="client_file" accept="application/json" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Google Analytics" />
		</footer>
	</form>
	<?php
		}
	?>
</div>