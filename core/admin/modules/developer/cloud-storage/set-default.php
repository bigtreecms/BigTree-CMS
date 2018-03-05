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
			$service_names = array("amazon" => "Amazon S3","rackspace" => "Rackspace Cloud Files","google" => "Google Cloud Storage");
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
			?>
			<fieldset>
				<label for="storage_cdn_domain"><?=Text::translate("CloudFront Distribution Domain")?> <small>(<?=Text::translate("optional")?>)</small></label>
				<input id="storage_cdn_domain" type="text" name="cdn_domain" value="<?=BigTree::safeEncode($storage->Settings->CDNDomain)?>" />
			</fieldset>
			<?php
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<?php
		}
	} else {
		$storage->Settings["Service"] = "local";
		$storage->Setting->save();
		
		Utils::growl("Developer","Changed Default Storage");
		Router::redirect(DEVELOPER_ROOT);
	}
?>