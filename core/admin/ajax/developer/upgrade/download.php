<?
	@unlink(SERVER_ROOT."cache/update.zip");
	// Try fopen first
	$resource = @fopen($_POST["file"],"r");
	if ($resource) {
		file_put_contents(SERVER_ROOT."cache/update.zip",$resource);
	} else {
		$file = fopen(SERVER_ROOT."cache/update.zip","w");
		$curl = curl_init();
		curl_setopt_array($curl,array(
			CURLOPT_URL => $_POST["file"],
			CURLOPT_TIMEOUT => 300,
			CURLOPT_FILE => $file
		));
		curl_exec($curl);
		fclose($file);
	}
?>