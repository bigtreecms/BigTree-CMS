<?php
	namespace BigTree;
	
	/*
	 	Function: extensions/get-available-updates
			Returns an array of extensions that have available updates.
		
		Method: GET
	*/
	
	API::requireLevel(2);
	API::requireMethod("GET");
	
	$extensions = DB::getAll("extensions");
	$query = [];
	$updates =[];
	
	foreach ($extensions as $extension) {
		if (empty($extension["updates_ignored"])) {
			$query[] = "extensions[]=".urlencode($extension->ID);
		}
	}
	
	$version_info = cURL::request("https://www.bigtreecms.org/ajax/extensions/version/?".implode("&", $query), false, [
		CURLOPT_CONNECTTIMEOUT => 1,
		CURLOPT_TIMEOUT => 5
	]);
	$version_info =  array_filter((array) @json_decode($version_info, true));
	
	foreach ($extensions as &$extension) {
		if (empty($extension["updates_ignored"])) {
			if (file_exists(SERVER_ROOT."extensions/".$extension["id"]."/manifest.json")) {
				$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/".$extension["id"]."/manifest.json"), true);
				
				if (!empty($version_info[$extension["id"]]["revision"]) &&
					intval($manifest["revision"]) < intval($version_info[$extension["id"]]["revision"]))
				{
					$info = $version_info[$extension["id"]];
					$updates[] = [
						"id" => $extension["id"],
						"version" => $info["version"],
						"compatibility" => $info["compatibility"]
					];
				}
			}
		}
	}
	
	API::sendResponse($updates);
	