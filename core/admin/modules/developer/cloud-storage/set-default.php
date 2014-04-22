<?
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
		<input type="hidden" name="service" value="<?=htmlspecialchars($_POST["service"])?>" />
		<summary><h2><?=$service_names[$_POST["service"]]?></h2></summary>
		<section>
			<label>Existing Container/Bucket <small>(this should be used exclusively by BigTree, if left blank BigTree will make its own)</small></label>
			<select name="container">
				<option></option>
				<? foreach ($containers as $container) { ?>
				<option value="<?=htmlspecialchars($container["name"])?>"<? if ($storage->Settings->Container == $container["name"] && $storage->Settings->Service == $_POST["service"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($container["name"])?></option>
				<? } ?>
			</select>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</container>
<?
		}
	} else {
		$storage->Settings->Service = "local";
		$admin->growl("Developer","Changed Default Storage");
		BigTree::redirect(DEVELOPER_ROOT);
	}
?>