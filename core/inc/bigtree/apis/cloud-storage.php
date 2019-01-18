<?php
	/*
		Class: BigTreeCloudStorage
			A cloud storage interface class that provides service agnostic calls on top of various cloud storage platforms.
	*/
	
	require_once SERVER_ROOT."core/inc/bigtree/apis/_oauth.base.php";
	
	class BigTreeCloudStorage extends BigTreeOAuthAPIBase {
		
		// These are only applicable to Google Cloud Storage
		public $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		public $AWSRegions = [
			"us-east-1" => "US East (N. Virginia)",
			"us-east-2" => "US East (Ohio)",
			"us-west-1" => "US West (N. California)",
			"us-west-2" => "US West (Oregon)",
			"ca-central-1" => "Canada (Central)",
			"ap-south-1" => "Asia Pacific (Mumbai)",
			"ap-northeast-2" => "Asia Pacific (Seoul)",
			"ap-northeast-3" => "Asia Pacific (Osaka-Local)",
			"ap-southeast-1" => "Asia Pacific (Singapore)",
			"ap-southeast-2" => "Asia Pacific (Sydney)",
			"ap-northeast-1" => "Asia Pacific (Tokyo)",
			"cn-north-1" => "China (Beijing)",
			"cn-northwest-1" => "China (Ningxia)",
			"eu-central-1" => "EU (Frankfurt)",
			"eu-west-1" => "EU (Ireland)",
			"eu-west-2" => "EU (London)",
			"eu-west-3" => "EU (Paris)",
			"sa-east-1" => "South America (São Paulo)"
		];
		public $EndpointURL = "https://www.googleapis.com/storage/v1/";
		public $Errors = [];
		public $HTTPResponseCode = false;
		public $MimeExtensions = [
			"jpg" => "image/jpeg", "jpeg" => "image/jpeg", "gif" => "image/gif",
			"png" => "image/png", "ico" => "image/x-icon", "pdf" => "application/pdf",
			"tif" => "image/tiff", "tiff" => "image/tiff", "svg" => "image/svg+xml",
			"svgz" => "image/svg+xml", "swf" => "application/x-shockwave-flash",
			"zip" => "application/zip", "gz" => "application/x-gzip",
			"tar" => "application/x-tar", "bz" => "application/x-bzip",
			"bz2" => "application/x-bzip2", "rar" => "application/x-rar-compressed",
			"exe" => "application/x-msdownload", "msi" => "application/x-msdownload",
			"cab" => "application/vnd.ms-cab-compressed", "txt" => "text/plain",
			"asc" => "text/plain", "htm" => "text/html", "html" => "text/html",
			"css" => "text/css", "js" => "text/javascript",
			"xml" => "text/xml", "xsl" => "application/xsl+xml",
			"ogg" => "application/ogg", "mp3" => "audio/mpeg", "wav" => "audio/x-wav",
			"avi" => "video/x-msvideo", "mpg" => "video/mpeg", "mpeg" => "video/mpeg",
			"mov" => "video/quicktime", "flv" => "video/x-flv", "php" => "text/x-php"
		];
		public $NextPage = null;
		public $OAuthVersion = "1.0";
		public $RequestType = "header";
		public $Scope = "https://www.googleapis.com/auth/devstorage.full_control";
		public $TokenURL = "https://accounts.google.com/o/oauth2/token";
		
		private $CloudFrontClient;
		private $S3Client;
		
		/*
			Constructor:
				Retrieves the current desired service and settings.

			Parameters:
				service - The service to use (amazon, rackspace, google) — if this is left empty it will use $this->Settings["service"] which can be set and auto saves.
		*/
		
		public function __construct($service = false) {
			parent::__construct("bigtree-internal-cloud-storage", "Cloud Storage", "org.bigtreecms.api.cloud-storage", false);
			$this->Service = $service ? $service : $this->Settings["service"];
			
			// Set OAuth Return URL for Google Cloud Storage
			$this->ReturnURL = ADMIN_ROOT."developer/cloud-storage/google/return/";
			
			// Retrieve a fresh token for Rackspace Cloud Files
			if ($this->Service == "rackspace") {
				if (!isset($this->Settings["rackspace"]["token_expiration"]) || $this->Settings["rackspace"]["token_expiration"] < time()) {
					$this->getRackspaceToken();
				}
				
				$this->RackspaceAPIEndpoint = $this->Settings["rackspace"]["endpoints"][$this->Settings["rackspace"]["region"]];
				$this->RackspaceCDNEndpoint = $this->Settings["rackspace"]["cdn_endpoints"][$this->Settings["rackspace"]["region"]];
			}
			
			// Setup S3 Client for Amazon
			if ($this->Service == "amazon") {
				$this->setupAmazon();
			}
		}
		
		/*
			Function: _getRackspaceURL
				Checks to see if the bucket is CDN enabled, if it is we use the CDN URL, otherwise the private URL.

			Parameters:
				container - Container name
				pointer - File pointer

			Returns:
				A URL
		*/
		
		public function _getRackspaceURL($container, $pointer) {
			if ($this->Settings["rackspace"]["container_cdn_urls"][$container]) {
				return $this->Settings["rackspace"]["container_cdn_urls"][$container]."/$pointer";
			} else {
				// See if we can get the container's CDN URL
				$cdn = false;
				$response = BigTree::cURL($this->RackspaceCDNEndpoint."/$container", "", [CURLOPT_CUSTOMREQUEST => "HEAD", CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Settings["rackspace"]["token"]]]);
				$lines = explode("\n", $response);
				
				foreach ($lines as $line) {
					if (substr($line, 0, 10) == "X-Cdn-Uri:") {
						$cdn = trim(substr($line, 10));
					}
				}
				
				if ($cdn) {
					$this->Settings["rackspace"]["container_cdn_urls"][$container] = $cdn;
					$this->saveSettings();
					
					return "$cdn/$pointer";
				}
			}
			
			return $this->RackspaceAPIEndpoint."/$container/$pointer";
		}
		
		/*
			Function: _hash
				Used for HMAC hashing internally.

			Parameters:
				secret - Secret used to hash.
				string - String to hash.

			Returns:
				Hashed string.
		*/
		
		protected function _hash($secret, $string) {
			if (extension_loaded("hash")) {
				return base64_encode(hash_hmac('sha1', $string, $secret, true));
			}
			
			return base64_encode(pack('H*', sha1((str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))).pack('H*', sha1((str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))).$string)))));
		}
		
		/*
			Function: copyFile
				Copies a file from one container/location to another container/location.
				Rackspace Cloud Files ignores "access" — public/private is controlled through the container only.

			Parameters:
				source_container - The container the file is stored in.
				source_pointer - The full file path inside the container.
				destination_container - The container to copy the source file to.
				destination_pointer - The full file path to store the copied file
				public - true to make publicly accessible, defaults to false (this setting is ignored in Rackspace Cloud Files and is ignored in Amazon S3 if the bucket's policy is set to public)
				
			Returns:
				The URL of the file if successful.
		*/
		
		public function copyFile($source_container, $source_pointer, $destination_container, $destination_pointer, $public = false) {
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$response = $this->S3Client->copyObject([
						"Bucket" => $destination_container,
						"ACL" => $public ? "public-read" : "",
						"CopySource" => "/".$source_container."/".rawurlencode($source_pointer),
						"Key" => $destination_pointer
					]);
					
					return $response["ObjectURL"];
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				global $bigtree;
				
				BigTree::cURL($this->RackspaceAPIEndpoint."/$source_container/$source_pointer", false, [CURLOPT_CUSTOMREQUEST => "COPY", CURLOPT_HTTPHEADER => ["Destination: /$destination_container/$destination_pointer", "X-Auth-Token: ".$this->Settings["rackspace"]["token"]]]);
				
				if ($bigtree["last_curl_response_code"] == "201") {
					return $this->_getRackspaceURL($destination_container, $destination_pointer);
				}
				
				return false;
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$response = $this->call("b/$source_container/o/".rawurlencode($source_pointer)."/copyTo/b/$destination_container/o/".rawurlencode($destination_pointer), "{}", "POST");
				
				if (isset($response->id)) {
					// Set the access control level if it's publicly accessible
					if ($public) {
						$this->call("b/$destination_container/o/".rawurlencode($destination_pointer)."/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
					}
					
					return "//storage.googleapis.com/$destination_container/$destination_pointer";
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: createContainer
				Creates a new container/bucket.
				Rackspace Cloud Files: If public is set to true the container be CDN-enabled and all of its contents will be publicly readable.
				Amazon: If public is set to true the bucket will have a policy making everything inside the bucket public.
				Google: If public is set to true the bucket will set the default access control on objects to public but they can be later changed.

			Parameters:
				name - Container name (keep in mind this must be unique among all other containers)
				public - true for public, defaults to false
			
			Returns:
				true if successful.
		*/
		
		public function createContainer($name, $public = false) {
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$this->S3Client->createBucket([
						"Bucket" => $name,
						"ACL" => $public ? "public-read" : ""
					]);
					
					return true;
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				global $bigtree;
				
				$this->callRackspace($name, "", [CURLOPT_PUT => true]);
				
				if ($bigtree["last_curl_response_code"] == 201) {
					// CDN Enable this container if it's public
					if ($public) {
						BigTree::cURL($this->RackspaceCDNEndpoint."/$name", "", [CURLOPT_PUT => true, CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Settings["rackspace"]["token"], "X-Cdn-Enabled: true"]]);
					}
					
					return true;
				} else {
					return false;
				}
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$request = ["name" => $name];
				
				if ($public) {
					$request["defaultObjectAcl"] = [
						["role" => "READER", "entity" => "allAuthenticatedUsers"],
						["role" => "READER", "entity" => "allUsers"]
					];
				}
				
				$response = $this->call("b?project=".$this->Settings["project"], json_encode($request), "POST");
				
				if (isset($response->id)) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: createFile
				Creates a new file in the given container.
				Rackspace Cloud Files ignores "access" — public/private is controlled through the container only.

			Parameters:
				contents - What to write to the file.
				container - Container name.
				pointer - The full file path inside the container.
				public - true to make publicly accessible, defaults to false (this setting is ignored in Rackspace Cloud Files and is ignored in Amazon S3 if the bucket's policy is set to public)
				type - MIME type (defaults to using the file extension, falls back to text/plain)
			
			Returns:
				The URL of the file if successful.
		*/
		
		public function createFile($contents, $container, $pointer, $public = false, $type = "") {
			$extension = strtolower(pathinfo($pointer, PATHINFO_EXTENSION));
			
			// Get destination mime type
			if (!$type) {
				$type = isset($this->MimeExtensions[$extension]) ? $this->MimeExtensions[$extension] : "text/plain";
			}
			
			// Amazon S3
			if ($this->Service == "amazon") {
				$contents = strlen($contents) ? $contents : " ";
				
				try {
					$response = $this->S3Client->putObject([
						"Bucket" => $container,
						"ContentType" => $type,
						"ContentLength" => strlen($contents),
						"Body" => $contents,
						"Key" => $pointer,
						"ACL" => ($public ? "public-read" : "private")
					]);
					
					return $response["ObjectURL"];
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				global $bigtree;
				
				BigTree::cURL($this->RackspaceAPIEndpoint."/$container/$pointer", $contents, [CURLOPT_CUSTOMREQUEST => "PUT", CURLOPT_HTTPHEADER => ["Content-Length" => strlen($contents), "X-Auth-Token: ".$this->Settings["rackspace"]["token"]]]);
				
				if ($bigtree["last_curl_response_code"] == "201") {
					return $this->_getRackspaceURL($container, $pointer);
				}
				
				return false;
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$encoded_pointer = rawurlencode($pointer);
				$response = json_decode(BigTree::cURL("https://www.googleapis.com/upload/storage/v1/b/$container/o?name=$encoded_pointer&uploadType=media", $contents, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: $type", "Content-Length: ".strlen($contents), "Authorization: Bearer ".$this->Settings["token"]]]));
				
				if (isset($response->id)) {
					// Set the access control level if it's publicly accessible
					if ($public) {
						$this->call("b/$container/o/$encoded_pointer/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
					}
					
					return "//storage.googleapis.com/$container/$pointer";
				} else {
					foreach ($response->error->errors as $error) {
						$this->Errors[] = $error;
					}
					
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: createFolder
				Creates a new folder in the given container.

			Parameters:
				container - Container name.
				pointer - The full folder path inside the container.
			
			Returns:
				true if successful.
		*/
		
		public function createFolder($container, $pointer) {
			return $this->createFile("", $container, rtrim($pointer, "/")."/");
		}
		
		/*
			Function: deleteContainer
				Deletes a container/bucket.
				Containers must be empty to be deleted.

			Parameters:
				container - Container to delete.
			
			Returns:
				true if successful.
		*/
		
		public function deleteContainer($container) {
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$response = $this->S3Client->deleteBucket(["Bucket" => $container]);
					
					return true;
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				global $bigtree;
				
				$this->callRackspace($container, "", [CURLOPT_CUSTOMREQUEST => "DELETE"]);
				
				if ($bigtree["last_curl_response_code"] == 204) {
					return true;
				} elseif ($bigtree["last_curl_response_code"] == 404) {
					$this->Errors[] = ["message" => "Container was not found."];
				} elseif ($bigtree["last_curl_response_code"] == 409) {
					$this->Errors[] = ["message" => "Container could not be deleted because it is not empty."];
				}
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$error_count = count($this->Errors);
				$this->call("b/$container", false, "DELETE");
				
				if (count($this->Errors) > $error_count) {
					return false;
				}
				
				return true;
			} else {
				return false;
			}
			
			return false;
		}
		
		/*
			Function: deleteFile
				Deletes a file from the given container.

			Parameters:
				container - The container the file is stored in.
				pointer - The full file path inside the container.

			Returns:
				true if successful
		*/
		
		public function deleteFile($container, $pointer) {
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$response = $this->S3Client->deleteObject(["Bucket" => $container, "Key" => $pointer]);
					
					return true;
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				global $bigtree;
				
				$this->callRackspace("$container/$pointer", "", [CURLOPT_CUSTOMREQUEST => "DELETE"]);
				
				if ($bigtree["last_curl_response_code"] == 204) {
					return true;
				}
				
				return false;
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$error_count = count($this->Errors);
				$this->call("b/$container/o/".rawurlencode($pointer), false, "DELETE");
				
				if (count($this->Errors) > $error_count) {
					return false;
				}
				
				return true;
			} else {
				return false;
			}
		}
		
		/*
			Function: getAuthenticatedFileURL
				Returns a URL that is valid for a limited amount of time to a private file.

			Parameters:
				container - The container the file is in.
				pointer - The full file path inside the container.
				expires - The number of seconds before this URL will expire.

			Returns:
				A URL.
		*/
		
		public function getAuthenticatedFileURL($container, $pointer, $expires) {
			$expires += time();
			
			// Amazon S3
			if ($this->Service == "amazon") {
				$pointer = str_replace(['%2F', '%2B'], ['/', '+'], rawurlencode($pointer));
				
				return "//$container.s3.amazonaws.com/".$pointer."?AWSAccessKeyId=".$this->Settings["amazon"]["key"]."&Expires=$expires&Signature=".urlencode($this->_hash($this->Settings["amazon"]["secret"], "GET\n\n\n$expires\n/$container/$pointer"));
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				// If we don't have a Temp URL key already set, we need to make one
				if (!$this->Settings["rackspace"]["temp_url_key"]) {
					// See if we already have one
					$response = BigTree::cURL($this->RackspaceAPIEndpoint, false, [
						CURLOPT_CUSTOMREQUEST => "HEAD",
						CURLOPT_HEADER => true,
						CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Settings["rackspace"]["token"]]
					]);
					$headers = explode("\n", $response);
					
					foreach ($headers as $header) {
						if (substr($header, 0, 28) == "X-Account-Meta-Temp-Url-Key:") {
							$this->Settings["rackspace"]["temp_url_key"] = trim(substr($header, 29));
						}
					}
					
					// If we don't have an existing one, make up our own
					if (!$this->Settings["rackspace"]["temp_url_key"]) {
						$this->Settings["rackspace"]["temp_url_key"] = uniqid();
						BigTree::cURL($this->RackspaceAPIEndpoint, false, [
							CURLOPT_CUSTOMREQUEST => "POST",
							CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Settings["rackspace"]["token"], "X-Account-Meta-Temp-Url-Key: ".$this->Settings["rackspace"]["temp_url_key"]]
						]);
					}

					$this->saveSettings();
				}
				
				list($domain, $client_id) = explode("/v1/", $this->RackspaceAPIEndpoint);
				$hash = urlencode(hash_hmac("sha1", "GET\n$expires\n/v1/$client_id/$container/$pointer", $this->Settings["rackspace"]["temp_url_key"]));
				
				return $this->RackspaceAPIEndpoint."/$container/$pointer?temp_url_sig=$hash&temp_url_expires=$expires";
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				if (!function_exists('openssl_x509_read')) {
					throw new Exception("PHP's OpenSSL extension is required to use authenticated URLs with Google Cloud Storage.");
				}
				
				if (!$this->Settings["private_key"] || !$this->Settings["certificate_email"]) {
					throw new Exception("You must upload your Google Cloud Storage private key and set your Certificate Email Address to use authenticated URLs.");
				}
				
				// Google's default password for these is "notasecret"
				$certificates = [];
				
				if (!openssl_pkcs12_read(file_get_contents($this->Settings["private_key"]), $certificates, "notasecret")) {
					throw new Exception("Unable to parse Google Cloud Storage private key file:".openssl_error_string());
				}
				
				$private_key = openssl_pkey_get_private($certificates["pkey"]);
				
				// Sign the string
				$encoded_pointer = str_replace(" ", "%20", $pointer);
				openssl_sign("GET\n\n\n$expires\n/$container/$encoded_pointer", $signature, $private_key, "sha256");
				
				return "//storage.googleapis.com/$container/$pointer?GoogleAccessId=".$this->Settings["certificate_email"]."&Expires=$expires&Signature=".urlencode(base64_encode($signature));
			} else {
				return false;
			}
		}
		
		/*
			Function: getCloudFrontDistributions
				Returns an array of distributions.

			Returns:
				An array or null if the API call failed.
		*/
		
		public function getCloudFrontDistributions() {
			$ca_cert = file_exists(SERVER_ROOT."cache/bigtree-ca-cert.pem") ? SERVER_ROOT."cache/bigtree-ca-cert.pem" : SERVER_ROOT."core/cacert.pem";
			$cloudfront = new Aws\CloudFront\CloudFrontClient([
				"version" => "latest",
				"region" => $this->Settings["amazon"]["region"],
				"credentials" => [
					"key" => $this->Settings["amazon"]["key"],
					"secret" => $this->Settings["amazon"]["secret"]
				],
				"http" => [
					"verify" => $ca_cert
				]
			]);
			
			$distributions = [];
			
			try {
				$continue = true;
				$marker = "";
				
				while ($continue) {
					$response = $this->CloudFrontClient->listDistributions(["Marker" => $marker]);
					
					if (isset($response["DistributionList"]["Items"])) {
						foreach ($response["DistributionList"]["Items"] as $item) {
							$dist = [
								"id" => $item["Id"],
								"domain" => $item["DomainName"],
								"aliases" => []
							];
							
							if (isset($item["Aliases"]["Items"])) {
								foreach ($item["Aliases"]["Items"] as $alias) {
									$dist["aliases"][] = $alias;
								}
							}
							
							$distributions[] = $dist;
						}
					}
					
					if ($response["IsTruncated"]) {
						$continue = true;
						$marker = $response["NextMarker"];
					} else {
						$continue = false;
					}
				}
				
				return $distributions;
			} catch (Exception $e) {
				$this->Errors[] = $e->getMessage();
				
				return null;
			}
		}
		
		/*
			Function: getContainer
				Lists the contents of a container/bucket.

			Parameters:
				container - The name of the container.
				simple - Simple mode (returns only a flat array with name/path/size, defaults to false)

			Returns:
				An array of the contents of the container.
		*/
		
		public function getContainer($container, $simple = false) {
			$tree = ["folders" => [], "files" => []];
			$flat = [];
			
			// Amazon S3
			if ($this->Service == "amazon") {
				$continue = true;
				$marker = "";
				
				while ($continue) {
					try {
						$response = $this->S3Client->listObjects(["Bucket" => $container, "Marker" => $marker]);
						$x = 0;
						
						foreach ($response["Contents"] as $item) {
							$x++;
							
							if ($x == 1 && $marker) {
								continue;
							}
							
							if ($simple) {
								$flat[] = [
									"name" => $item["Key"],
									"path" => $item["Key"],
									"size" => $item["Size"]
								];
							} else {
								$flat[$item["Key"]] = [
									"name" => $item["Key"],
									"path" => $item["Key"],
									"updated_at" => date("Y-m-d H:i:s", strtotime((string) $item["LastModified"])),
									"etag" => $item["ETag"],
									"size" => $item["Size"],
									"owner" => [
										"name" => $item["Owner"]["DisplayName"],
										"id" => $item["Owner"]["ID"]
									],
									"storage_class" => $item["StorageClass"]
								];
							}
						}
						
						$continue = false;
						
						// Multi-page
						if ($response["IsTruncated"]) {
							$continue = true;
							$marker = $item["Key"];
						}
					} catch (Exception $e) {
						$this->Errors[] = $e->getMessage();
						
						return false;
					}
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				$response = $this->callRackspace($container);
				
				if (is_array($response)) {
					foreach ($response as $item) {
						if ($simple) {
							$flat[] = [
								"name" => (string) $item->name,
								"path" => (string) $item->name,
								"size" => (int) $item->bytes
							];
						} else {
							$flat[(string) $item->name] = [
								"name" => (string) $item->name,
								"path" => (string) $item->name,
								"updated_at" => date("Y-m-d H:i:s", strtotime($item->last_modified)),
								"etag" => (string) $item->hash,
								"size" => (int) $item->bytes
							];
						}
					}
				} else {
					return false;
				}
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$response = $this->call("b/$container/o");
				
				if (isset($response->kind) && $response->kind == "storage#objects") {
					if (is_array($response->items)) {
						foreach ($response->items as $item) {
							if ($simple) {
								$flat[] = [
									"name" => (string) $item->name,
									"path" => (string) $item->name,
									"size" => (int) $item->size
								];
							} else {
								$flat[(string) $item->name] = [
									"name" => (string) $item->name,
									"path" => (string) $item->name,
									"updated_at" => date("Y-m-d H:i:s", strtotime($item->updated)),
									"etag" => (string) $item->etag,
									"size" => (int) $item->size,
									"owner" => [
										"name" => (string) $item->owner->entity,
										"id" => (string) $item->owner->entityId
									]
								];
							}
						}
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
			
			if ($simple) {
				return $flat;
			}
			
			foreach ($flat as $raw_item) {
				$keys = explode("/", $raw_item["name"]);
				
				// We're going to use by reference vars to figure out which folder to place this in
				if (count($keys) > 1) {
					$folder = &$tree;
					
					for ($i = 0; $i < count($keys); $i++) {
						// Last part of the key and also has a . so we know it's actually a file
						if ($i == count($keys) - 1 && strpos($keys[$i], ".") !== false) {
							$raw_item["name"] = $keys[$i];
							$folder["files"][] = $raw_item;
						} else {
							if ($keys[$i]) {
								if (!isset($folder["folders"][$keys[$i]])) {
									$folder["folders"][$keys[$i]] = ["folders" => [], "files" => []];
								}
					
								$folder = &$folder["folders"][$keys[$i]];
							}
						}
					}
				} else {
					$tree["files"][] = $raw_item;
				}
			}
			
			return ["flat" => $flat, "tree" => $tree];
		}
		
		/*
			Function: getFile
				Returns a file from the given container.

			Parameters:
				container - The container the file is stored in.
				pointer - The full file path inside the container.

			Returns:
				A binary stream of data or false if the file is not found or not allowed.
		*/
		
		public function getFile($container, $pointer) {
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$response = $this->S3Client->getObject(["Bucket" => $container, "Key" => $pointer]);
					
					return $response["Body"];
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				return BigTree::cURL($this->RackspaceAPIEndpoint."/$container/$pointer", false, [CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Settings["rackspace"]["token"]]]);
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				// We do a manual call because the "call" method always assumes the response is JSON.
				return BigTree::cURL("https://storage.googleapis.com/$container/$pointer", false, [CURLOPT_HTTPHEADER => ["Authorization: Bearer ".$this->Settings["token"]]]);
			} else {
				return false;
			}
		}
		
		/*
			Function: getFolder
				Returns the folder "contents" from a container.

			Parameters:
				container - Either a string of the name of the container the folder resides in OR the response from a previous getContainer call.
				folder - The full folder path inside the container.

			Returns:
				A keyed array of files and folders inside the folder or false if the folder was not found.
		*/
		
		public function getFolder($container, $folder) {
			if (!is_array($container)) {
				$container = $this->getContainer($container);
			}
			
			$folder_parts = explode("/", trim($folder, "/"));
			$tree = $container["tree"];
			
			foreach ($folder_parts as $part) {
				$tree = isset($tree["folders"][$part]) ? $tree["folders"][$part] : false;
			}
			
			return $tree;
		}
		
		/*
			Function: getRackspaceToken
				Gets a new access token for the Rackspace Cloud Files API.
		*/
		
		public function getRackspaceToken() {
			$j = json_decode(BigTree::cURL("https://identity.api.rackspacecloud.com/v2.0/tokens", json_encode([
				"auth" => [
					"RAX-KSKEY:apiKeyCredentials" => [
						"username" => $this->Settings["rackspace"]["username"],
						"apiKey" => $this->Settings["rackspace"]["api_key"]
					]
				]
			]), [
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => ["Content-Type: application/json"]]
			));
			
			if (isset($j->access->token)) {
				$this->Settings["rackspace"]["token"] = $j->access->token->id;
				$this->Settings["rackspace"]["token_expiration"] = strtotime($j->access->token->expires);
				$this->Settings["rackspace"]["endpoints"] = [];
				$this->Settings["rackspace"]["cdn_endpoints"] = [];
				
				// Get API endpoints
				foreach ($j->access->serviceCatalog as $service) {
					if ($service->name == "cloudFiles") {
						foreach ($service->endpoints as $endpoint) {
							$this->Settings["rackspace"]["endpoints"][$endpoint->region] = (string) $endpoint->publicURL;
						}
					} elseif ($service->name == "cloudFilesCDN") {
						foreach ($service->endpoints as $endpoint) {
							$this->Settings["rackspace"]["cdn_endpoints"][$endpoint->region] = (string) $endpoint->publicURL;
						}
					}
				}

				$this->saveSettings();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: getS3BucketExists
				Returns true if a bucket of the specified name already exists.

			Parameters:
				bucket

			Returns:
				true if bucket exists, else false
		*/
		
		public function getS3BucketExists($bucket) {
			try {
				// In this case, the bucket exists AND we have access to it
				$this->S3Client->headBucket(["Bucket" => $bucket]);
				
				return true;
			} catch (Exception $e) {
				$message = $e->getMessage();
				
				// Bucket exists, but this key/secret doesn't have access
				if (strpos($message, "403") !== false) {
					return true;
				}
				
				return false;
			}
		}
		
		
		/*
			Function: getS3BucketPage
				Returns a page of contents of an Amazon S3 bucket.

			Parameters:
				bucket - The name of the bucket.
				marker - A page marker token (or null to start from the beginning)

			Returns:
				An array a page of the contents of the container (name, path, and size only).
				$this->NextPage is set to the next page marker token
		*/
		
		public function getS3BucketPage($bucket, $marker = null) {
			$page = [];
			
			if ($this->Service != "amazon") {
				throw new Exception("Method getS3BucketPage is only compatible with Amazon S3.");
			}
			
			try {
				$response = $this->S3Client->listObjects(["Bucket" => $bucket, "Marker" => $marker]);
				$x = 0;
				
				if (is_array($response["Contents"])) {
					foreach ($response["Contents"] as $item) {
						$x++;
						
						if ($x == 1 && $marker) {
							continue;
						}
						
						$page[] = [
							"name" => $item["Key"],
							"path" => $item["Key"],
							"size" => $item["Size"]
						];
					}
				}
				
				if (!empty($response["IsTruncated"])) {
					$this->NextPage = $item["Key"];
				}
				
				return $page;
			} catch (Exception $e) {
				$this->Errors[] = $e->getMessage();
				
				return false;
			}
		}
		
		/*
			Function: getS3BucketRegion
				Returns the proper region for the specified bucket.

			Parameters:
				bucket - A bucket name

			Returns:
				A region name or false if the bucket does not exist or is not accessible.
		*/
		
		public function getS3BucketRegion($bucket) {
			$client = new Aws\S3\S3MultiRegionClient([
				"version" => "latest",
				"credentials" => [
					"key" => $this->Settings["amazon"]["key"],
					"secret" => $this->Settings["amazon"]["secret"]
				]
			]);
			
			return $client->determineBucketRegion($bucket) ?: false;
		}
		
		/*
			Function: invalidateCache
				Invalidates the CloudFront cache for a given pointer.

			Parameters:
				pointer - The pointer to invalidate

			Returns:
				true if successful
		*/
		
		public function invalidateCache($pointer) {
			if ($this->Service != "amazon") {
				trigger_error("Cache invalidation is only supported for AWS storage.");
			}
			
			try {
				$this->CloudFrontClient->createInvalidation([
					"DistributionId" => $this->Settings["amazon"]["cloudfront_distribution"],
					"InvalidationBatch" => [
						"CallerReference" => microtime(true),
						"Paths" => [
							"Quantity" => 1,
							"Items" => ["/".ltrim($pointer, "/")]
						]
					]
				]);
				
				return true;
			} catch (Exception $e) {
				return false;
			}
		}
		
		/*
			Function: listContainers
				Lists containers/buckets that are available in this cloud account.

			Returns:
				An array of container names.
		*/
		
		public function listContainers() {
			$containers = [];
			
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$response = $this->S3Client->listBuckets();
					
					foreach ($response["Buckets"] as $bucket) {
						$containers[] = [
							"name" => $bucket["Name"],
							"created_at" => date("Y-m-d H:i:s", strtotime((string) $bucket["CreationDate"]))
						];
					}
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				$response = $this->callRackspace();
				
				if (is_array($response)) {
					foreach ($response as $item) {
						$containers[] = ["name" => (string) $item->name];
					}
				} else {
					return false;
				}
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$response = $this->call("b", ["project" => $this->Settings["project"]]);
				
				if (isset($response->kind) && $response->kind == "storage#buckets") {
					if (is_array($response->items)) {
						foreach ($response->items as $item) {
							$containers[] = [
								"name" => (string) $item->name,
								"created_at" => date("Y-m-d H:i:s", strtotime($item->timeCreated)),
								"location" => (string) $item->location,
								"storage_class" => (string) $item->storageClass
							];
						}
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
			
			return $containers;
		}
		
		/*
			Function: makeFilePublic
				Makes a file readable to the public.
				Rackspace Cloud Files does not support this method.

			Parameters:
				container - The container/bucket the file is in
				pointer - The pointer to the file.

			Returns:
				The true successful, otherwise false
		*/
		
		public function makeFilePublic($container, $pointer) {
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$this->S3Client->putObjectAcl([
						"Bucket" => $container,
						"Key" => $pointer,
						"ACL" => "public-read"
					]);
					
					return true;
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$response = $this->call("b/$container/o/".rawurlencode($pointer)."/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
				
				if ($response) {
					return true;
				}
			// Rackspace or no setup at all
			} else {
				throw new Exception("The current cloud service provider does not support this method.");
			}
		}
		
		/*
			Function: resetCache
				Clears the bigtree_caches table of container data and resets it with new data.

			Parameters:
				data - An array of file data from a container
		*/
		
		public function resetCache($data) {
			SQL::delete("bigtree_caches", ["identifier" => "org.bigtreecms.cloudfiles"]);
			
			foreach ($data as $item) {
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
		
		/*
			Function: setupAmazon
				Sets up the S3/CloudFront clients for Amazon calls.
		*/
		
		public function setupAmazon() {
			$ca_cert = file_exists(SERVER_ROOT."cache/bigtree-ca-cert.pem") ? SERVER_ROOT."cache/bigtree-ca-cert.pem" : SERVER_ROOT."core/cacert.pem";

			$this->S3Client = new Aws\S3\S3Client([
				"version" => "latest",
				"region" => $this->Settings["amazon"]["region"],
				"credentials" => [
					"key" => $this->Settings["amazon"]["key"],
					"secret" => $this->Settings["amazon"]["secret"]
				],
				"http" => [
					"verify" => $ca_cert
				]
			]);
			
			$this->CloudFrontClient = new Aws\CloudFront\CloudFrontClient([
				"version" => "latest",
				"region" => $this->Settings["amazon"]["region"],
				"credentials" => [
					"key" => $this->Settings["amazon"]["key"],
					"secret" => $this->Settings["amazon"]["secret"]
				],
				"http" => [
					"verify" => $ca_cert
				]
			]);
		}
		
		/*
			Function: uploadFile
				Creates a new file in the given container.
				Rackspace Cloud Files ignores "access" — public/private is controlled through the container only.

			Parameters:
				file - The file to upload.
				container - Container name.
				pointer - The full file path inside the container (if left empty the file's current name will be used and the root of the bucket)
				public - true to make publicly accessible, defaults to false (this setting is ignored in Rackspace Cloud Files and is ignored in Amazon S3 if the bucket's policy is set to public)
				type - MIME type (defaults to "text/plain")
			
			Returns:
				The URL of the file if successful.
		*/
		
		public function uploadFile($file, $container, $pointer = false, $public = false) {
			// Default the pointer to the name of the file if not provided.
			if (!$pointer) {
				$path_info = BigTree::pathInfo($file);
				$pointer = $path_info["basename"];
			} else {
				$path_info = BigTree::pathInfo($pointer);
			}
			
			// Get destination mime type
			$content_type = isset($this->MimeExtensions[strtolower($path_info["extension"])]) ? $this->MimeExtensions[strtolower($path_info["extension"])] : "application/octet-stream";
			
			// Amazon S3
			if ($this->Service == "amazon") {
				try {
					$response = $this->S3Client->putObject([
						"Bucket" => $container,
						"ContentType" => $content_type,
						"ContentLength" => filesize($file),
						"SourceFile" => $file,
						"Key" => $pointer,
						"ACL" => ($public ? "public-read" : "private")
					]);
					
					return $response["ObjectURL"];
				} catch (Exception $e) {
					$this->Errors[] = $e->getMessage();
					
					return false;
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {
				global $bigtree;
				
				$file_pointer = fopen($file, "r");
				BigTree::cURL($this->RackspaceAPIEndpoint."/$container/$pointer", false, [CURLOPT_PUT => true, CURLOPT_INFILE => $file_pointer, CURLOPT_HTTPHEADER => ["Content-Length" => filesize($file), "X-Auth-Token: ".$this->Settings["rackspace"]["token"]]]);
				fclose($file_pointer);
				
				if ($bigtree["last_curl_response_code"] == "201") {
					return $this->_getRackspaceURL($container, $pointer);
				}
				
				return false;
			// Google Cloud Storage
			} elseif ($this->Service == "google") {
				$encoded_pointer = urlencode($pointer);
				$file_pointer = fopen($file, "r");
				$response = json_decode(BigTree::cURL("https://www.googleapis.com/upload/storage/v1/b/$container/o?name=$encoded_pointer&uploadType=media", false, [CURLOPT_INFILE => $file_pointer, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: $content_type", "Content-Length: ".filesize($file), "Authorization: Bearer ".$this->Settings["token"]]]));
				fclose($file_pointer);
				
				if (isset($response->id)) {
					// Set the access control level if it's publicly accessible
					if ($public) {
						$this->call("b/$container/o/$encoded_pointer/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
					}
					
					return "//storage.googleapis.com/$container/$pointer";
				} else {
					foreach ($response->error->errors as $error) {
						$this->Errors[] = $error;
					}
					
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: callRackspace
				cURL wrapper for Rackspace.

			Parameters:
				endpoint - Endpoint to hit.
				data - Request body data.
				curl_options - Additional cURL options.
		*/
		
		public function callRackspace($endpoint = "", $data = false, $curl_options = []) {
			$curl_options = $curl_options + [CURLOPT_HTTPHEADER => ["Accept: application/json", "X-Auth-Token: ".$this->Settings["rackspace"]["token"]]];
			
			return json_decode(BigTree::cURL($this->RackspaceAPIEndpoint.($endpoint ? "/$endpoint" : ""), $data, $curl_options));
		}
		
	}
