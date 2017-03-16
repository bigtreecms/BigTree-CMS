<?
	$admin->verifyCSRFToken();
	
	// Remove existing update zips
	@unlink(SERVER_ROOT."cache/update.zip");

	// Get download info
	$url = $cms->cacheGet("org.bigtreecms.downloads",$_POST["key"]);
	$cms->cacheDelete("org.bigtreecms.downloads",$_POST["key"]);

	// Try fopen first
	$resource = @fopen($url,"r");
	if ($resource) {
		BigTree::putFile(SERVER_ROOT."cache/update.zip",$resource);
	} else {
		$file = fopen(SERVER_ROOT."cache/update.zip","w");
		$curl = curl_init();
		curl_setopt_array($curl,array(
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_FILE => $file
		));
		curl_exec($curl);
		fclose($file);
	}
?>