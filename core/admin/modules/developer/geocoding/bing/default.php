<?
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>geocoding/bing/activate/">
		<section>
			<p>To use the Bing Maps Geocoder API you will need a Bing Maps Key. To acquire a key, please reference <a href="http://msdn.microsoft.com/en-us/library/ff428642.aspx" target="_blank">Getting a Bing Maps Key</a> at MSDN.</p>
			<hr />
			<fieldset>
				<label>Bing Maps Key</label>
				<input type="text" name="bing_key" value="<?=htmlspecialchars($geocoding_service["bing_key"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Bing Maps Geocoder" />
		</footer>
	</form>
</div>