<?php
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>geocoding/mapquest/activate/">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p>To use the MapQuest Geocoder API you will need a MapQuest AppKey. To acquire a key, please reference <a href="https://business.mapquest.com/products/geocoding-api/" target="_blank">MapQuest Geocoding API Web Service</a>.</p>
			<hr />
			<fieldset>
				<label>MapQuest AppKey</label>
				<input type="text" name="mapquest_key" value="<?=htmlspecialchars($geocoding_service["mapquest_key"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate MapQuest Geocoder" />
		</footer>
	</form>
</div>