<?php
	/*
		Class: BigTree\cURL
			Provides an interface for making cURL requests.
	*/
	
	namespace BigTree;
	
	class cURL {
		
		public static $Error;
		public static $ResponseCode;
		
		/*
			Function: request
				Makes a request to a given URL and returns the response.
			
			Parameters:
				url - The URL to retrieve / POST to.
				post - A key/value pair array of things to POST (optional).
				options - A key/value pair of extra cURL options (optional).
				strict_security - Force SSL verification of the host and peer if true (optional, defaults to false).
				output_file - A file location to dump the output of the request to (optional, replaces return value).
			
			Returns:
				The string response from the URL.
		*/
		
		public static function request(string $url, $post = null, array $options = [], bool $strict_security = true,
									   ?string $output_file = null, bool $updating_bundle = false): ?string {
			global $bigtree;
			
			$cert_bundle = SERVER_ROOT."cache/bigtree-ca-cert.pem";
			
			// Use the core bundle which may be out of date to grab the latest bundle
			if (!file_exists($cert_bundle) || empty(file_get_contents($cert_bundle))) {
				FileSystem::copyFile(SERVER_ROOT."core/cacert.pem", $cert_bundle);
			}
			
			// Check CA cert bundle has been updated in the past month
			if (!$updating_bundle && filemtime($cert_bundle) < time()) {
				$cert_bundle_new = SERVER_ROOT."cache/bigtree-ca-cert-new.pem";
				static::request("https://curl.haxx.se/ca/cacert.pem", false, [], true, $cert_bundle_new, true);
				@unlink($cert_bundle);
				@rename($cert_bundle_new, $cert_bundle);
			}
			
			// Startup cURL and set the URL
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_URL, $url);
			
			// Determine whether we're forcing valid SSL on the peer and host
			if ($strict_security) {
				curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($handle, CURLOPT_CAINFO, $cert_bundle);
				curl_setopt($handle, CURLOPT_CAPATH, $cert_bundle);
			} else {
				curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
			}
			
			// Limit request to 5 seconds less than max execution time
			$max_execution_time = ini_get("max_execution_time");
			
			if ($max_execution_time !== 0) {
				curl_setopt($handle, CURLOPT_TIMEOUT,  $max_execution_time - 5);
			}
			
			// If we're returning to a file we setup a file pointer rather than waste RAM capturing to a variable
			if ($output_file) {
				$file_pointer = fopen($output_file, "w");
				curl_setopt($handle, CURLOPT_FILE, $file_pointer);
			} else {
				curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			}
			
			// Setup post data
			if (!empty($post)) {
				// Use cURLFile for any file uploads
				if (function_exists("curl_file_create") && is_array($post)) {
					foreach ($post as &$post_field) {
						if (substr($post_field, 0, 1) == "@" && file_exists(substr($post_field, 1))) {
							$post_field = curl_file_create(substr($post_field, 1));
						}
					}
					
					unset($post_field);
				}
				
				curl_setopt($handle, CURLOPT_POSTFIELDS, $post);
			}
			
			// Any additional cURL options
			if (is_array($options) && count($options)) {
				foreach ($options as $key => $opt) {
					curl_setopt($handle, $key, $opt);
				}
			}
			
			$output = curl_exec($handle);
			
			static::$ResponseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			
			if (empty(static::$ResponseCode)) {
				static::$Error = curl_error($handle);
			}
			
			curl_close($handle);
			
			// If we're outputting to a file, close the handle and return nothing
			if ($file_pointer) {
				fclose($file_pointer);
				
				return $output;
			}
			
			return $output;
		}
		
	}