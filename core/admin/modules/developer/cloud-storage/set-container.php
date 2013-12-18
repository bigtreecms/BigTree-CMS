<?
	$storage->Service = $_POST["service"];
	$cloud = new BigTreeCloudStorage($_POST["service"]);
	if ($_POST["container"]) {
		$storage->Container = $_POST["container"];
	} else {
		// We're only going to try to get a unique bucket 10 times to prevent an infinite loop
		$x = 0;
		$success = false;
		while (!$success && $x < 10) {
			$container = uniqid("bigtree-container-",true);
			$success = $cloud->createContainer($container);
		}
		if ($success) {
			$storage->Container = $container;
		} else {
			$admin->growl("Developer","Failed to create container.","error");
			BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
		}
	}
	
	$container = $cloud->getContainer($storage->Container);
	if ($container === false) {
		$admin->growl("Developer","Failed to read container.","error");
		BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
	}
	$files = array();
	foreach ($container["flat"] as $item) {
		$files[$item["path"]] = array("name" => $item["name"],"path" => $item["path"],"size" => $item["size"]);
	}
	$storage->Files = $files;
	
	$admin->growl("Developer","Changed Default Storage");
	BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
?>