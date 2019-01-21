<?php
	namespace BigTree;
	
	$geocoding_service = Setting::value("bigtree-internal-geocoding-service");
?>
<form method="post" action="<?=DEVELOPER_ROOT?>geocoding/google/activate/">
	<?php CSRF::drawPOSTToken(); ?>
	<section>
		<p><?=Text::translate('To use the Google Maps Geocoder API you will need a Google Maps API Key. To acquire a key, please reference <a href=":google_link:" target="_blank">Getting Started</a> at Google Maps Platform.', false, [":google_link:" => "https://developers.google.com/maps/documentation/geocoding/start"])?></p>
		<hr />
		<fieldset>
			<label>Google Maps API Key</label>
			<input type="text" name="google_key" value="<?=Text::htmlEncode($geocoding_service["google_key"])?>" />
		</fieldset>
	</section>
	<footer>
		<input type="submit" class="button blue" value="Activate Google Maps Geocoder" />
	</footer>
</form>