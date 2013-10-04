<div class="container">
	<?
		$geo = new BigTreeGeocoding;
		$api = new BigTreeYahooBOSSAPI;
		if ($api->Connected && $geo->Service == "yahoo-boss") {
	?>
	<section>
		<p>The Yahoo BOSS Geocoder is connected.</p>
	</section>
	<?
		} elseif ($api->Connected) {
	?>
	<section>
		<p>Yahoo BOSS API services have already been configured but Yahoo BOSS is not currently the active geocoder.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>geocoding/yahoo-boss/switch/" class="button blue">Activate Yahoo BOSS Geocoder</a>
	</footer>
	<?
		} else { 
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>geocoding/yahoo-boss/activate/">
		<section>
			<p>To use the Yahoo BOSS API you will need a Yahoo BOSS paid account. Yahoo BOSS provides commercial, per-query pricing (i.e. $6.00 per 1,000 queries). BigTree will cache properly geocoded addresses as to limit your query usage. To set up an account, follow the steps below:</p>
			<hr />
			<ol>
				<li>Sign Up For a Yahoo ID and read about Yahoo BOSS Geo Services at the <a href="http://developer.yahoo.com/boss/geo/" target="_blank">Yahoo Developer Network</a>.</li>
				<li><a href="https://developer.apps.yahoo.com/dashboard/createKey.html" target="_blank">Create a new Project</a> and be sure to check "I want to enable Yahoo! BOSS for this project".</li>
				<li>On your project page, there should be a link to "Manage Billing" in the BOSS section. Fill out your billing information.</li>
				<li>Under the "Permissions" section of your project, select any of the checkboxes (Yahoo Projects don't work properly as BOSS-only for some reason or another) and click "Save and Change Consumer Key"</li>
				<li>Copy and paste the "Consumer Key" and "Consumer Secret" below.</li>
				<li>Follow the OAuth process to authorize BigTree to run geocoding on your behalf.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Consumer Key</label>
				<input type="text" name="key" value="<?=htmlspecialchars($api->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Consumer Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($api->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Yahoo BOSS Geocoder" />
		</footer>
	</form>
	<?
		}
	?>
</div>