<?php
	header("Content-type: text/json");

	$storage = new BigTreeStorage;
	$cloud = new BigTreeCloudStorage("amazon");
	$data = $cloud->getS3BucketPage($storage->Settings->Container, $_GET["marker"]);

	if ($data === false) {
		echo BigTree::json([
			"error" => array_pop($cloud->Errors)
		]);

		die();
	}

	foreach ($data as $item) {
		if (!SQL::exists("bigtree_caches", ["key" => $item["path"], "identifier" => "org.bigtreecms.cloudfiles"])) {
			SQL::insert("bigtree_caches", [
				"identifier" => "org.bigtreecms.cloudfiles",
				"key" => $item["path"],
				"value" => [
					"name" => $item["name"],
					"path" => $item["path"],
					"size" => $item["size"]
				]
			]);
		}
	}

	if ($cloud->NextPage) {
		if ($_GET["marker"]) {
			$response = "Completed page beginning with file marker ".htmlspecialchars($_GET["marker"]);
		} else {
			$response = "Completed first page";
		}

		echo BigTree::json([
			"response" => $response,
			"marker" => $cloud->NextPage,
			"complete" => false
		]);
	} else {
		echo BigTree::json([
			"complete" => true
		]);
	}
