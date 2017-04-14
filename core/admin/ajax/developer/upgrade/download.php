<?php
	namespace BigTree;
	
	CSRF::verify();

	// Remove existing update zips
	FileSystem::deleteFile(SERVER_ROOT."cache/update.zip");

	// Get download info
	$url = Cache::get("org.bigtreecms.downloads", $_POST["key"]);
	Cache::delete("org.bigtreecms.downloads", $_POST["key"]);

	// Try fopen first
	$resource = @fopen($url, "r");
	if ($resource) {
		FileSystem::createFile(SERVER_ROOT."cache/update.zip", $resource);
	} else {
		$file = fopen(SERVER_ROOT."cache/update.zip", "w");
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_FILE => $file
		));
		curl_exec($curl);
		fclose($file);
	}
	