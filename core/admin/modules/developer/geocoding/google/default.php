<?php
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>geocoding/google/activate/">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p>To use the Google Maps Geocoder API you will need a Google Maps API Key. To acquire a key, please reference <a href="https://developers.google.com/maps/documentation/geocoding/start" target="_blank">Getting Started</a> at Google Maps Platform.</p>
			<hr />
			<fieldset>
				<label>Google Maps API Key</label>
				<input type="text" name="google_key" value="<?=htmlspecialchars($geocoding_service["google_key"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Google Maps Geocoder" />
		</footer>
	</form>
</div>