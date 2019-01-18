<?php
	$admin->verifyCSRFToken();

	$storage = new BigTreeStorage;

	if ($_POST["service"] != "local") {
		$cloud = new BigTreeCloudStorage($_POST["service"]);
		$containers = $cloud->listContainers();
		
		if ($containers === false) {
			$admin->growl("Developer","Invalid Cloud Storage Setup: ".ucwords($_POST["service"]),"error");
			BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
		} else {
			$service_names = array("amazon" => "Amazon S3","rackspace" => "Rackspace Cloud Files","google" => "Google Cloud Storage");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/set-container/">
		<?php $admin->drawCSRFToken() ?>
		<input type="hidden" name="service" value="<?=htmlspecialchars($_POST["service"])?>" />
		<summary><h2><?=$service_names[$_POST["service"]]?></h2></summary>
		<section>
			<fieldset>
				<label for="cloud_field_container">Existing Container/Bucket <small>(this should be used exclusively by BigTree, if left blank BigTree will make its own)</small></label>
				<select id="cloud_field_container" name="container">
					<option></option>
					<?php foreach ($containers as $container) { ?>
					<option value="<?=htmlspecialchars($container["name"])?>"<?php if ($storage->Settings->Container == $container["name"] && $storage->Settings->Service == $_POST["service"]) { ?> selected="selected"<?php } ?>><?=htmlspecialchars($container["name"])?></option>
					<?php } ?>
				</select>
			</fieldset>
			<?php
				if ($_POST["service"] == "amazon") {
					$cloudfront_distribution = $storage->Cloud->Settings["amazon"]["cloudfront_distribution"];
					$cloudfront_ssl = $storage->Cloud->Settings["amazon"]["cloudfront_ssl"];
					$cloudfront_domain = $storage->Cloud->Settings["amazon"]["cloudfront_domain"];
					$distributions = $cloud->getCloudFrontDistributions();

					if (is_array($distributions) && count($distributions)) {
			?>
			<fieldset>
				<label for="cloud_field_distribution">CloudFront Distribution <small>(optional)</small></label>
				<select id="cloud_field_distribution" name="cloudfront_distribution">
					<option></option>
					<?php
						foreach ($distributions as $dist) {
							if (!count($dist["aliases"])) {
					?>
					<option value="<?=htmlspecialchars($dist["id"])?>"<?php if ($cloudfront_distribution == $dist["id"]) { ?> selected<?php } ?> data-domain="<?=htmlspecialchars($dist["domain"])?>"><?=$dist["domain"]?> (<?=$dist["id"]?>)</option>
					<?php
							}
							
							foreach ($dist["aliases"] as $alias) {
					?>
					<option value="<?=htmlspecialchars($dist["id"])?>"<?php if ($cloudfront_distribution == $dist["id"]) { ?> selected<?php } ?> data-domain="<?=htmlspecialchars($alias)?>"><?=$alias?> (<?=$dist["id"]?>)</option>
					<?php
							}
						}
					?>
				</select>

				<input id="cloud_field_domain" name="cloudfront_domain" value="<?=$cloudfront_domain?>" type="hidden">
			</fieldset>

			<fieldset id="cloud_fieldset_https"<?php if (!$cloudfront_distribution) { ?> style="display: none;"<?php } ?>>
				<input id="cloud_field_https" type="checkbox" name="cloudfront_ssl"<?php if ($cloudfront_ssl) { ?> checked<?php } ?>>
				<label for="cloud_field_https" class="for_checkbox">CloudFront Domain Supports HTTPS</label>
			</fieldset>
			<?php
					}
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
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
		$storage->Settings->Service = "local";
		$storage->saveSettings();
		$admin->growl("Developer","Changed Default Storage");

		BigTree::redirect(DEVELOPER_ROOT);
	}
?>