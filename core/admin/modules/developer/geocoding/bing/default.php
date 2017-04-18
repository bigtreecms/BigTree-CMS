<?php
	namespace BigTree;

	$geocoding_service = Setting::value("bigtree-internal-geocoding-service");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>geocoding/bing/activate/">
	  <?php CSRF::drawPOSTToken(); ?>
		<section>
			<p><?=Text::translate('To use the Bing Maps Geocoder API you will need a Bing Maps Key. To acquire a key, please reference <a href=":key_url:" target="_blank">Getting a Bing Maps Key</a> at MSDN.', false, array(":key_url:" => "http://msdn.microsoft.com/en-us/library/ff428642.aspx"))?></p>
			<hr />
			<fieldset>
				<label for="bing_field_key"><?=Text::translate("Bing Maps Key")?></label>
				<input id="bing_field_key" type="text" name="bing_key" value="<?=htmlspecialchars($geocoding_service["bing_key"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Activate Bing Maps Geocoder", true)?>" />
		</footer>
	</form>
</div>