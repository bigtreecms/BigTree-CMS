<?php
	/*
		Class: BigTree\CloudStorage\Rackspace
			A cloud storage interface class for Rackspace Cloud Files.
			See BigTree\CloudStorage\Provider for method definitions.
	*/
	
	namespace BigTree\CloudStorage;
	
	use BigTree\cURL;
	use stdClass;
	
	class Rackspace extends Provider {
		
		protected $CDNContainerURLs;
		protected $CDNEndpoints;
		protected $Endpoints;
		protected $TempURLKey;
		protected $Token;
		protected $TokenExpiration;
		
		public $CDNEndpoint = "";
		public $Endpoint = "";
		public $Key;
		public $Regions = [
			"ORD" => "Chicago, IL (USA)",
			"DFW" => "Dallas/Ft. Worth, TX (USA)",
			"HKG" => "Hong Kong",
			"LON" => "London (UK)",
			"IAD" => "Northern Virginia (USA)",
			"SYD" => "Sydney (Australia)"
		];
		public $Region;
		public $Username;
		
		function __construct() {
			parent::__construct();
			
			// Setup references to the main cloud storage setting
			$this->Active = &$this->Settings["rackspace"]["active"];
			$this->CDNContainerURLs = &$this->Settings["rackspace"]["container_cdn_urls"];
			$this->CDNEndpoints = &$this->Settings["rackspace"]["cdn_endpoints"];
			$this->Endpoints = &$this->Settings["rackspace"]["endpoints"];
			$this->Key = &$this->Settings["rackspace"]["api_key"];
			$this->Region = &$this->Settings["rackspace"]["region"];
			$this->TempURLKey = &$this->Settings["rackspace"]["temp_url_key"];
			$this->Token = &$this->Settings["rackspace"]["token"];
			$this->TokenExpiration = &$this->Settings["rackspace"]["token_expiration"];
			$this->Username = &$this->Settings["rackspace"]["username"];
			
			if ($this->Active) {
				if (!isset($this->TokenExpiration) || $this->TokenExpiration < time()) {
					$this->getToken();
				}
				
				// Current settings
				$this->Endpoint = $this->Endpoints[$this->Region];
				$this->CDNEndpoint = $this->CDNEndpoints[$this->Region];
			}
		}
		
		/*
			Function: callRackspace
				cURL wrapper for Rackspace API.

			Parameters:
				endpoint - Endpoint to hit.
				data - Request body data.
				curl_options - Additional cURL options.
		*/
		
		function callRackspace(string $endpoint = "", ?array $data = null, string $method = "GET",
							   array $curl_options = []): ?stdClass {
			// Add authentication headers and ask for JSON in return
			if (!is_array($curl_options[CURLOPT_HTTPHEADER])) {
				$curl_options[CURLOPT_HTTPHEADER] = [];
			}
			
			$curl_options[CURLOPT_HTTPHEADER][] = "Accept: application/json";
			$curl_options[CURLOPT_HTTPHEADER][] = "X-Auth-Token: ".$this->Token;
			
			// If the method isn't get, set proper curl options
			if ($method == "POST") {
				$curl_options[CURLOPT_POST] = true;
			} elseif ($method != "GET") {
				$curl_options[CURLOPT_CUSTOMREQUEST] = $method;
			}
			
			return json_decode(cURL::request($this->Endpoint.($endpoint ? "/$endpoint" : ""), $data, $curl_options));
		}
		
		// Implements Provider::copyFile
		function copyFile(string $source_container, string $source_pointer, string $destination_container,
						  string $destination_pointer, bool $public = false): ?string {
			cURL::request($this->Endpoint."/$source_container/$source_pointer", false, [
				CURLOPT_CUSTOMREQUEST => "COPY",
				CURLOPT_HTTPHEADER => [
					"Destination: /$destination_container/$destination_pointer",
					"X-Auth-Token: ".$this->Token
				]
			]);
			
			if (cURL::$ResponseCode == "201") {
				return $this->getURL($destination_container, $destination_pointer);
			}
			
			return null;
		}
		
		// Implements Provider::createContainer
		function createContainer(string $name, bool $public = false): ?bool {
			$this->callRackspace($name, "", "PUT", [CURLOPT_PUT => true]);
			
			if (cURL::$ResponseCode == 201) {
				// CDN Enable this container if it's public
				if ($public) {
					cURL::request($this->CDNEndpoint."/$name", false, [
						CURLOPT_PUT => true,
						CURLOPT_HTTPHEADER => [
							"X-Auth-Token: ".$this->Token,
							"X-Cdn-Enabled: true"
						]
					]);
				}
				
				return true;
			}
			
			return false;
		}
		
		// Implements Provider::createFile
		function createFile(string $contents, string $container, string $pointer, bool $public = false,
							string $type = "text/plain"): ?string {
			cURL::request($this->Endpoint."/$container/$pointer", $contents, [
				CURLOPT_CUSTOMREQUEST => "PUT",
				CURLOPT_HTTPHEADER => [
					"Content-Length" => strlen($contents),
					"X-Auth-Token: ".$this->Token
				]
			]);
			
			if (cURL::$ResponseCode == "201") {
				return $this->getURL($container, $pointer);
			}
			
			return null;
		}
		
		// Implements Provider::deleteContainer
		function deleteContainer(string $container): ?bool {
			$this->callRackspace($container, "", "DELETE", [CURLOPT_CUSTOMREQUEST => "DELETE"]);
			
			if (cURL::$ResponseCode == 204) {
				return true;
			} elseif (cURL::$ResponseCode == 404) {
				$this->Errors[] = ["message" => "Container was not found."];
			} elseif (cURL::$ResponseCode == 409) {
				$this->Errors[] = ["message" => "Container could not be deleted because it is not empty."];
			}
			
			return false;
		}
		
		// Implements Provider::deleteFile
		function deleteFile(string $container, string $pointer): ?bool {
			$this->callRackspace("$container/$pointer", "", "DELETE", [CURLOPT_CUSTOMREQUEST => "DELETE"]);
			
			if (cURL::$ResponseCode == 204) {
				return true;
			}
			
			return false;
		}
		
		// Implements Provider::getAuthenticatedFileURL
		function getAuthenticatedFileURL(string $container, string $pointer, int $expires): ?string {
			$expires += time();
			
			// If we don't have a Temp URL key already set, we need to make one
			if (!$this->TempURLKey) {
				// See if we already have one
				$response = cURL::request($this->Endpoint, false, [
					CURLOPT_CUSTOMREQUEST => "HEAD",
					CURLOPT_HEADER => true,
					CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Token]
				]);
				
				$headers = explode("\n", $response);
				
				foreach ($headers as $header) {
					if (substr($header, 0, 28) == "X-Account-Meta-Temp-Url-Key:") {
						$this->TempURLKey = trim(substr($header, 29));
					}
				}
				
				// If we don't have an existing one, make up our own
				if (!$this->TempURLKey) {
					$this->TempURLKey = uniqid();
					
					cURL::request($this->Endpoint, false, [
						CURLOPT_CUSTOMREQUEST => "POST",
						CURLOPT_HTTPHEADER => [
							"X-Auth-Token: ".$this->Token,
							"X-Account-Meta-Temp-Url-Key: ".$this->TempURLKey
						]
					]);
				}
			}
			
			list($domain, $client_id) = explode("/v1/", $this->Endpoint);
			$hash = urlencode(hash_hmac("sha1", "GET\n$expires\n/v1/$client_id/$container/$pointer", $this->TempURLKey));
			
			return $this->Endpoint."/$container/$pointer?temp_url_sig=$hash&temp_url_expires=$expires";
		}
		
		// Implements Provider::getContainer
		function getContainer(string $container, bool $simple = false): ?array {
			$flat = [];
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
				trigger_error('BigTree\CloudStorage\Rackspace::getContainer call failed.', E_USER_WARNING);
				
				return null;
			}
			
			return $simple ? $flat : ["tree" => $this->getContainerTree($flat), "flat" => $flat];
		}
		
		// Implements Provider::getFile
		function getFile(string $container, string $pointer): ?string {
			return cURL::request($this->Endpoint."/$container/$pointer", false, [
				CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Token]
			]);
		}
		
		// Internal method for refreshing a Rackspace token
		function getToken() {
			$data = [
				"auth" => [
					"RAX-KSKEY:apiKeyCredentials" => [
						"username" => $this->Username,
						"apiKey" => $this->Key
					]
				]
			];
			
			$response = cURL::request("https://identity.api.rackspacecloud.com/v2.0/tokens",
									  json_encode($data),
									  [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: application/json"]]);
			
			$response_object = json_decode($response);
			
			if (isset($response_object->access->token)) {
				$this->Token = $response_object->access->token->id;
				$this->TokenExpiration = strtotime($response_object->access->token->expires);
				$this->Endpoints = [];
				$this->CDNEndpoints = [];
				
				// Get API endpoints
				foreach ($response_object->access->serviceCatalog as $service) {
					if ($service->name == "cloudFiles") {
						foreach ($service->endpoints as $endpoint) {
							$this->Endpoints[$endpoint->region] = (string) $endpoint->publicURL;
						}
					} elseif ($service->name == "cloudFilesCDN") {
						foreach ($service->endpoints as $endpoint) {
							$this->CDNEndpoints[$endpoint->region] = (string) $endpoint->publicURL;
						}
					}
				}
				
				return true;
			}
			
			return false;
		}
		
		// Internal method for getting the live URL of an asset
		function getURL(string $container, string $pointer): string {
			if ($this->CDNContainerURLs[$container]) {
				return $this->CDNContainerURLs[$container]."/$pointer";
			} else {
				// See if we can get the container's CDN URL
				$cdn = false;
				$response = cURL::request($this->CDNEndpoint."/$container", false, [
					CURLOPT_CUSTOMREQUEST => "HEAD",
					CURLOPT_HEADER => true,
					CURLOPT_HTTPHEADER => ["X-Auth-Token: ".$this->Settings["rackspace"]["token"]]
				]);
				$lines = explode("\n", $response);
				
				foreach ($lines as $line) {
					if (substr($line, 0, 10) == "X-Cdn-Uri:") {
						$cdn = trim(substr($line, 10));
					}
				}
				
				if ($cdn) {
					$this->CDNContainerURLs[$container] = $cdn;
					
					return "$cdn/$pointer";
				}
			}
			
			return $this->Endpoint."/$container/$pointer";
		}
		
		// Implements Provider::listContainers
		function listContainers(): ?array {
			$containers = [];
			$response = $this->call();
			
			if (is_array($response)) {
				foreach ($response as $item) {
					$containers[] = ["name" => (string) $item->name];
				}
			} else {
				return null;
			}
			
			return $containers;
		}
		
		// Implements Provider::uploadFile
		function uploadFile(string $file, string $container, ?string $pointer = null, bool $public = false): ?string {
			// No target destination, just use root folder w/ file name
			if (!$pointer) {
				$path_info = pathinfo($file);
				$pointer = $path_info["basename"];
			}
			
			// Open the file pointer for curl to upload from
			$file_pointer = fopen($file, "r");
			
			cURL::request($this->Endpoint."/$container/$pointer", false, [
				CURLOPT_PUT => true,
				CURLOPT_INFILE => $file_pointer,
				CURLOPT_HTTPHEADER => [
					"Content-Length" => filesize($file),
					"X-Auth-Token: ".$this->Token
				]
			]);
			
			fclose($file_pointer);
			
			if (cURL::$ResponseCode == "201") {
				return $this->getURL($container, $pointer);
			}
			
			return false;
		}
		
	}