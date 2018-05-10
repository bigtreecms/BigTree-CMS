<?
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
		<? $admin->drawCSRFToken() ?>
		<input type="hidden" name="service" value="<?=htmlspecialchars($_POST["service"])?>" />
		<summary><h2><?=$service_names[$_POST["service"]]?></h2></summary>
		<section>
			<fieldset>
				<label>Existing Container/Bucket <small>(this should be used exclusively by BigTree, if left blank BigTree will make its own)</small></label>
				<select name="container">
					<option></option>
					<? foreach ($containers as $container) { ?>
					<option value="<?=htmlspecialchars($container["name"])?>"<? if ($storage->Settings->Container == $container["name"] && $storage->Settings->Service == $_POST["service"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($container["name"])?></option>
					<? } ?>
				</select>
			</fieldset>
			<?php
				if ($_POST["service"] == "amazon") {
			?>
			<fieldset>
				<label>CloudFront Distribution Domain <small>(optional)</small></label>
				<input type="text" name="cdn_domain" value="<?=BigTree::safeEncode($storage->Settings->CDNDomain)?>" />
			</fieldset>
			<?php
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<?
		}
	} else {
		$storage->Settings->Service = "local";
		$admin->growl("Developer","Changed Default Storage");
		BigTree::redirect(DEVELOPER_ROOT);
	}
?>