<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$storage = new Storage;

	if ($_POST["service"] != "local") {
		$cloud = false;
		
		if ($_POST["service"] == "amazon") {
			$cloud = new CloudStorage\Amazon;
		} elseif ($_POST["service"] == "rackspace") {
			$cloud = new CloudStorage\Rackspace;
		} elseif ($_POST["service"] == "google") {
			$cloud = new CloudStorage\Google;
		}

		$containers = $cloud->listContainers();
		
		if ($containers === false) {
			Utils::growl("Developer","Invalid Cloud Storage Setup: ".ucwords($_POST["service"]),"error");
			Router::redirect(DEVELOPER_ROOT."cloud-storage/");
		} else {
			$service_names = [
				"amazon" => "Amazon S3",
				"rackspace" => "Rackspace Cloud Files",
				"google" => "Google Cloud Storage"
			];
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/set-container/">
		<?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="service" value="<?=htmlspecialchars($_POST["service"])?>" />
		<div class="container_summary"><h2><?=$service_names[$_POST["service"]]?></h2></div>
		<section>
			<fieldset>
				<label for="storage_field_container"><?=Text::translate("Existing Container/Bucket")?> <small>(<?=Text::translate("this should be used exclusively by BigTree, if left blank BigTree will make its own")?>)</small></label>
				<select id="storage_field_container" name="container">
					<option></option>
					<?php foreach ($containers as $container) { ?>
					<option value="<?=htmlspecialchars($container["name"])?>"<?php if ($storage->Settings["Container"] == $container["name"] && $storage->Settings["Service"] == $_POST["service"]) { ?> selected="selected"<?php } ?>><?=htmlspecialchars($container["name"])?></option>
					<?php } ?>
				</select>
			</fieldset>
			<?php
				if ($_POST["service"] == "amazon") {
					$distributions = $cloud->getCloudFrontDistributions();
					
					if (is_array($distributions) && count($distributions)) {
			?>
			<fieldset>
				<label for="cloud_field_distribution"><?=Text::translate("CloudFront Distribution <small>(optional)</small>")?></label>
				<select id="cloud_field_distribution" name="cloudfront_distribution">
					<option></option>
					<?php
						foreach ($distributions as $distribution) {
							if (!count($distribution["aliases"])) {
					?>
					<option value="<?=htmlspecialchars($distribution["id"])?>"<?php if ($cloud->CloudFrontDistribution == $distribution["id"]) { ?> selected<?php } ?>data-domain="<?=htmlspecialchars($distribution["domain"])?>"><?=$distribution["domain"]?> (<?=$distribution["id"]?>)</option>
					<?php
							}
							
							foreach ($distribution["aliases"] as $alias) {
					?>
					<option value="<?=htmlspecialchars($distribution["id"])?>"<?php if ($cloud->CloudFrontDistribution == $distribution["id"]) { ?> selected<?php } ?>data-domain="<?=htmlspecialchars($alias)?>"><?=$alias?> (<?=$distribution["id"]?>)</option>
					<?php
							}
						}
					?>
				</select>

				<input id="cloud_field_domain" name="cloudfront_domain" value="<?=Text::htmlEncode($cloud->CloudFrontDomain)?>" type="hidden">
			</fieldset>

			<fieldset id="cloud_fieldset_https"<?php if (empty($cloud->CloudFrontDistribution)) { ?> style="display: none;"<?php } ?>>
				<input id="cloud_field_https" type="checkbox" name="cloudfront_ssl"<?php if (!empty($cloud->CloudFrontSSL)) { ?> checked<?php } ?>>
				<label for="cloud_field_https" class="for_checkbox"><?=Text::translate("CloudFront Domain Supports HTTPS")?></label>
			</fieldset>
			<?php
					}
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<script>
	$("#cloud_field_distribution").change(function() {
		if ($(this).val()) {
			$("#cloud_fieldset_https").show();
		} else {
			$("#cloud_fieldset_https").hide();
		}

		$("#cloud_field_domain").val($(this).find(":selected").data("domain"));
	});
</script>
<?php
		}
	} else {
		$storage->Settings["Service"] = "local";
		
		Setting::updateValue("bigtree-internal-storage", $storage->Settings, true);
		Utils::growl("Developer","Changed Default Storage");
		Router::redirect(DEVELOPER_ROOT);
	}
?>