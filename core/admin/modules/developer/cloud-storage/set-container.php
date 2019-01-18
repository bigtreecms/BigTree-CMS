<?php
	$admin->verifyCSRFToken();

	$storage = new BigTreeStorage;
	$cloud = new BigTreeCloudStorage($_POST["service"]);
	
	$storage->Settings->Service = $_POST["service"];

	if ($_POST["container"]) {
		$storage->Settings->Container = $_POST["container"];

		// If we're using Rackspace, we need to explicitly CDN enable this container.
		if ($_POST["service"] == "rackspace") {
			BigTree::cURL($cloud->RackspaceCDNEndpoint."/".$_POST["container"],"",array(CURLOPT_PUT => true,CURLOPT_HTTPHEADER => array("X-Auth-Token: ".$cloud->Settings["rackspace"]["token"],"X-Cdn-Enabled: true")));
		}

		// Set AWS specifics
		if ($_POST["service"] == "amazon") {			
			$cloud->Settings["amazon"]["cloudfront_distribution"] = $_POST["cloudfront_distribution"];
			$cloud->Settings["amazon"]["cloudfront_domain"] = $_POST["cloudfront_domain"];
			$cloud->Settings["amazon"]["cloudfront_ssl"] = $_POST["cloudfront_ssl"];
			$cloud->Settings["amazon"]["region"] = $cloud->getS3BucketRegion($_POST["container"]);
		}
	} else {
		// We're only going to try to get a unique bucket 10 times to prevent an infinite loop
		$x = 0;
		$success = false;
		
		while (!$success && $x < 10) {
			$container = $cms->urlify(uniqid("bigtree-container-",true));

			if ($_POST["service"] == "amazon") {
				if (!$cloud->getS3BucketExists($container)) {
					$success = $cloud->createContainer($container, true);
				}
			} else {
				$success = $cloud->createContainer($container, true);
			}

			$x++;
		}

		if ($success) {
			$storage->Settings->Container = $container;
		} else {
			$admin->growl("Developer","Failed to create container.","error");
			BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
		}
	}

	$storage->saveSettings();

	// For Amazon S3 we're going to redirect to do a paginated cache bust
	if ($_POST["service"] == "amazon") {
		$check = $cloud->getS3BucketPage($storage->Settings->Container);

		if ($check === false) {
			$admin->growl("Developer","Failed to read bucket.", "error");
			BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
		} else {
			$admin->growl("Developer","Changed Default Storage");
			BigTree::redirect(DEVELOPER_ROOT."cloud-storage/amazon/recache/");
		}
	}

	// Get a list of files
	$container = $cloud->getContainer($storage->Settings->Container,true);

	if ($container === false) {
		$admin->growl("Developer","Failed to read container.","error");
		BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
	}

	// Remove all existing cloud file caches and import new data
	$cloud->resetCache($container);

	$admin->growl("Developer","Changed Default Storage");
	BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
