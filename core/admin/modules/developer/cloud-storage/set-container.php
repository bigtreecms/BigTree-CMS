<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$storage = new Storage;
	$storage->Settings["Service"] = $_POST["service"];
	$cloud = false;
	$container = "";
	
	if ($_POST["service"] == "amazon") {
		$cloud = new CloudStorage\Amazon;
	} elseif ($_POST["service"] == "rackspace") {
		$cloud = new CloudStorage\Rackspace;
	} elseif ($_POST["service"] == "google") {
		$cloud = new CloudStorage\Google;
	}
	
	if ($_POST["container"]) {
		$storage->Settings["Container"] = $_POST["container"];
		
		// If we're using Rackspace, we need to explicitly CDN enable this container.
		if ($_POST["service"] == "rackspace") {
			cURL::request($cloud->CDNEndpoint."/".$_POST["container"], false, [CURLOPT_PUT => true, CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$cloud->Settings["rackspace"]["token"], "X-Cdn-Enabled: true"]]);
		}
		
		// Set AWS specifics
		if ($_POST["service"] == "amazon") {
			$cloud->Settings["amazon"]["cloudfront_distribution"] = $_POST["cloudfront_distribution"];
			$cloud->Settings["amazon"]["cloudfront_domain"] = $_POST["cloudfront_domain"];
			$cloud->Settings["amazon"]["cloudfront_ssl"] = $_POST["cloudfront_ssl"];
			$cloud->Settings["amazon"]["region"] = $cloud->getBucketRegion($_POST["container"]);
		}
		
	} else {
		// We're only going to try to get a unique bucket 10 times to prevent an infinite loop
		$x = 0;
		$success = false;
		
		while (!$success && $x < 10) {
			$container = Link::urlify(uniqid("bigtree-container-", true));
			
			if ($_POST["service"] == "amazon") {
				if (!$cloud->getBucketExists($container)) {
					$success = $cloud->createContainer($container, true);
				}
			} else {
				$success = $cloud->createContainer($container, true);
			}
			
			$x++;
		}
		
		if ($success) {
			$storage->Settings["Container"] = $container;
		} else {
			Utils::growl("Developer", "Failed to create container.", "error");
			Router::redirect(DEVELOPER_ROOT."cloud-storage/");
		}
	}
	
	// Get a list of files
	$container = $cloud->getContainer($storage->Settings["Container"], true);
	
	if ($container === false) {
		Utils::growl("Developer", "Failed to read container.", "error");
		Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	}
	
	// Remove all existing cloud file caches and import new data
	$cloud->resetCache($container);
	
	// Save storage settings
	$storage->Setting->save();
	
	Utils::growl("Developer", "Changed Default Storage");
	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
