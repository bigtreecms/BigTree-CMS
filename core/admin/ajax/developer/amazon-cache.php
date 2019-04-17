<?php
	namespace BigTree;
	
	header("Content-type: text/json");
	
	$storage = new Storage;
	$cloud = new CloudStorage\Amazon;
	$data = $cloud->getContainerPage($storage->Settings->Container, $_GET["marker"]);
	
	if ($data === false) {
		echo JSON::encode(["error" => array_pop($cloud->Errors)]);
		
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
		
		echo JSON::encode([
			"response" => $response,
			"marker" => $cloud->NextPage,
			"complete" => false
		]);
	} else {
		echo JSON::encode([
			"complete" => true
		]);
	}
