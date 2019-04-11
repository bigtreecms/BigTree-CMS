<?php
	/*
		Class: BigTree\CloudStorage\Google
			A cloud storage interface class for Google Cloud Storage.
			See BigTree\CloudStorage\Provider for method definitions.
	*/
	
	namespace BigTree\CloudStorage;
	
	use BigTree\cURL;
	
	class Google extends Provider
	{
		
		public $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		public $CertificateEmail;
		public $EndpointURL = "https://www.googleapis.com/storage/v1/";
		public $Errors = [];
		public $Key;
		public $OAuthVersion = "1.0";
		public $PrivateKey;
		public $Project;
		public $RequestType = "header";
		public $ReturnURL;
		public $Scope = "https://www.googleapis.com/auth/devstorage.full_control";
		public $Secret;
		public $Token;
		public $TokenURL = "https://accounts.google.com/o/oauth2/token";
		
		public function __construct()
		{
			// Tell the OAuth API to store things in "google" since we use a common setting for all APIs
			$this->SettingNamespace = "google";
			$this->ReturnURL = ADMIN_ROOT."developer/cloud-storage/google/return/";
			
			// Init OAuth
			parent::__construct();
			
			// Setup references to the main cloud storage setting
			$this->Active = &$this->Settings["google"]["active"];
			$this->CertificateEmail = &$this->Settings["google"]["certificate_email"];
			$this->Key = &$this->Settings["google"]["key"];
			$this->PrivateKey = &$this->Settings["google"]["private_key"];
			$this->Project = &$this->Settings["google"]["project"];
			$this->Secret = &$this->Settings["google"]["secret"];
			$this->Token = &$this->Settings["google"]["token"];
		}
		
		// Implements Provider::copyFile
		public function copyFile(string $source_container, string $source_pointer, string $destination_container,
						  string $destination_pointer, bool $public = false): ?string
		{
			$encoded_source_pointer = urlencode($source_pointer);
			$encoded_pointer = urlencode($destination_pointer);
			$response = $this->call("b/$source_container/o/$encoded_source_pointer/copyTo/b/$encoded_pointer/o/".rawurlencode($destination_pointer), "{}", "POST");
			
			if (isset($response->id)) {
				// Set the access control level if it's publicly accessible
				if ($public) {
					$this->call("b/$destination_container/o/$$encoded_pointer/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
				}
				
				return "//storage.googleapis.com/$destination_container/$destination_pointer";
			}
			
			return null;
		}
		
		// Implements Provider::createContainer
		public function createContainer(string $name, bool $public = false): ?bool
		{
			$request = ["name" => $name];
			
			if ($public) {
				$request["defaultObjectAcl"] = [
					["role" => "READER", "entity" => "allAuthenticatedUsers"],
					["role" => "READER", "entity" => "allUsers"]
				];
			}
			
			$response = $this->call("b?project=".$this->Project, json_encode($request), "POST");
			
			return isset($response->id) ? true : false;
		}
		
		// Implements Provider::createFile
		public function createFile(string $contents, string $container, string $pointer, bool $public = false,
							string $type = "text/plain"): ?string
		{
			$encoded_pointer = urlencode($pointer);
			$response = json_decode(cURL::request("https://www.googleapis.com/upload/storage/v1/b/$container/o?name=$encoded_pointer&uploadType=media", $contents, [
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => [
					"Content-Type: $type",
					"Content-Length: ".strlen($contents),
					"Authorization: Bearer ".$this->Token
				]
			]));
			
			// Success
			if (isset($response->id)) {
				// Set the access control level if it's publicly accessible
				if ($public) {
					$this->call("b/$container/o/$encoded_pointer/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
				}
				
				return "//storage.googleapis.com/$container/$pointer";
			}
			
			foreach ($response->error->errors as $error) {
				$this->Errors[] = $error;
			}
			
			return null;
		}
		
		// Implements Provider::deleteContainer
		public function deleteContainer(string $container): ?bool
		{
			$error_count = count($this->Errors);
			
			$this->call("b/$container", false, "DELETE");
			
			// The call stack will register a new error if it fails
			if (count($this->Errors) > $error_count) {
				return false;
			}
			
			return true;
		}
		
		// Implements Provider::deleteFile
		public function deleteFile(string $container, string $pointer): ?bool
		{
			$error_count = count($this->Errors);
			
			$this->call("b/$container/o/".urlencode($pointer), false, "DELETE");
			
			// The call stack will register a new error if it fails
			if (count($this->Errors) > $error_count) {
				return false;
			}
			
			return true;
		}
		
		// Implements Provider::getAuthenticatedFileURL
		public function getAuthenticatedFileURL(string $container, string $pointer, int $expires): ?string
		{
			$expires += time();
			
			if (!function_exists('openssl_x509_read')) {
				trigger_error("PHP's OpenSSL extension is required to use authenticated URLs with Google Cloud Storage.", E_USER_ERROR);
				
				return null;
			}
			
			if (!$this->PrivateKey || !$this->CertificateEmail) {
				trigger_error("You must upload your Google Cloud Storage private key and set your Certificate Email Address to use authenticated URLs.", E_USER_ERROR);
				
				return null;
			}
			
			// Google's default password for these is "notasecret"
			$certificates = [];
			
			if (!openssl_pkcs12_read(file_get_contents($this->PrivateKey), $certificates, "notasecret")) {
				trigger_error("Unable to parse Google Cloud Storage private key file:".openssl_error_string(), E_USER_ERROR);
				
				return null;
			}
			
			// Sign the string
			$private_key = openssl_pkey_get_private($certificates["pkey"]);
			$encoded_pointer = str_replace(" ", "%20", $pointer);
			openssl_sign("GET\n\n\n$expires\n/$container/$encoded_pointer", $signature, $private_key, "sha256");
			
			$signature = urlencode(base64_encode($signature));
			$access_id = $this->CertificateEmail;
			
			return "//storage.googleapis.com/$container/$pointer?GoogleAccessId=$access_id&Expires=$expires&Signature=$signature";
		}
		
		// Implements Provider::getContainer
		public function getContainer(string $container, bool $simple = false): ?array
		{
			$flat = [];
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
				trigger_error('BigTree\CloudStorage\Google::getContainer call failed.', E_USER_WARNING);
				
				return null;
			}
			
			return $simple ? $flat : ["tree" => $this->getContainerTree($flat), "flat" => $flat];
		}
		
		// Implements Provider::getfile
		public function getFile(string $container, string $pointer): ?string
		{
			$pointer = rawurlencode($pointer);
			
			return cURL::request("https://storage.googleapis.com/$container/$pointer", false, [
				CURLOPT_HTTPHEADER => ["Authorization: Bearer ".$this->Token]
			]);
		}
		
		// Implements Provider::listContainers
		public function listContainers(): ?array
		{
			$containers = [];
			$response = $this->call("b", ["project" => $this->Project]);
			
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
				return null;
			}
			
			return $containers;
		}
		
		// Implements Provider::makeFilePublic
		public function makeFilePublic(string $container, string $pointer): bool
		{
			$response = $this->call("b/$container/o/".rawurlencode($pointer)."/acl", json_encode(["entity" => "allUsers", "role" => "READER"]), "POST");
			
			if ($response) {
				return true;
			}
			
			return false;
		}
		
		// Implements Provider::uploadFile
		public function uploadFile(string $file, string $container, string $pointer = null, bool $public = false): ?string
		{
			// No target destination, just use root folder w/ file name
			if (!$pointer) {
				$path_info = pathinfo($file);
				$pointer = $path_info["basename"];
			}
			
			// Get MIME type
			$content_type = $this->getContentType($file);
			
			// Open file pointer for cURL to upload
			$file_pointer = fopen($file, "r");
			$encoded_pointer = rawurlencode($pointer);
			
			$response = json_decode(cURL::request("https://www.googleapis.com/upload/storage/v1/b/$container/o?name=$encoded_pointer&uploadType=media", false, [
				CURLOPT_INFILE => $file_pointer,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => [
					"Content-Type: $content_type",
					"Content-Length: ".filesize($file),
					"Authorization: Bearer ".$this->Token
				]
			]));
			
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
				
				return null;
			}
		}
		
	}
