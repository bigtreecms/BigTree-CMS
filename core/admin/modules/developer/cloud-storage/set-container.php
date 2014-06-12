<?
	$storage = new BigTreeStorage;
	$storage->Settings->Service = $_POST["service"];
	$cloud = new BigTreeCloudStorage($_POST["service"]);
	if ($_POST["container"]) {
		$storage->Settings->Container = $_POST["container"];
		// If we're using Rackspace, we need to explicitly CDN enable this container.
		if ($_POST["service"] == "rackspace") {
			BigTree::cURL($cloud->RackspaceCDNEndpoint."/".$_POST["container"],"",array(CURLOPT_PUT => true,CURLOPT_HTTPHEADER => array("X-Auth-Token: ".$cloud->Settings["rackspace"]["token"],"X-Cdn-Enabled: true")));
		}
	} else {
		// We're only going to try to get a unique bucket 10 times to prevent an infinite loop
		$x = 0;
		$success = false;
		while (!$success && $x < 10) {
			$container = $cms->urlify(uniqid("bigtree-container-",true));
			$success = $cloud->createContainer($container,true);
			$x++;
		}
		if ($success) {
			$storage->Settings->Container = $container;
		} else {
			$admin->growl("Developer","Failed to create container.","error");
			BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
		}
	}

	$container = $cloud->getContainer($storage->Settings->Container);
	if ($container === false) {
		$admin->growl("Developer","Failed to read container.","error");
		BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
	}

	// Remove all existing cloud file caches and import new data
	$cloud->resetCache($container["flat"]);

	$admin->growl("Developer","Changed Default Storage");
	BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
?>