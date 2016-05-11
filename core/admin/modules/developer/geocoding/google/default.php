<?php
	namespace BigTree;
?>
<div class="container">
	<section>
		<p><?=Text::translate('Use of Google\'s geocoding API is subject to the <a href=":terms_link:" target="_blank">Google Maps APIs Terms of Service</a>.', false, array(":terms_link:" => "https://developers.google.com/maps/terms#section_10_12"))?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>geocoding/google/activate/" class="button blue"><?=Text::translate("Activate Google Maps Geocoder")?></a>
	</footer>
</div>