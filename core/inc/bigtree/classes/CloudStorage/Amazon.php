<?php
	/*
		Class: BigTree\CloudStorage\Amazon
			A cloud storage interface class for Amazon S3.
			See BigTree\CloudStorage\Provider for method definitions.
	*/
	
	namespace BigTree\CloudStorage;
	
	class Amazon extends Provider {
		
		public $Key;
		public $Secret;
		
		public $HTTPResponseCode = false;
		
		function __construct() {
			parent::__construct();
			
			$this->Active = &$this->Settings["amazon"]["active"];
			$this->Key = &$this->Settings["amazon"]["key"];
			$this->Secret = &$this->Settings["amazon"]["secret"];
		}
		
		/*
			Function: callAmazon
				Calls the Amazon AWS API.

			Parameters:
				verb - The HTTP verb to use (GET, POST, PUT, DELETE, HEAD)
				bucket - The bucket to affect.
				uri - The resource to affect.
				params - Additional GET parameters.
				request_headers - Additional generic HTTP headers to set.
				amazon_headers - Specific Amazon AWS HTTP headers to set.
				data - POST or PUT data.
				file - File to upload.
		*/
		
		function callAmazon(string $verb = "GET", string $bucket = "", string $uri = "", array $params = [],
							array $request_headers = [], array $amazon_headers = [], ?array $data = null,
							?string $file = null): ?string {
			$uri = $uri ? "/".str_replace("%2F", "/", rawurlencode($uri)) : "/";
			
			if ($bucket) {
				// See if it's a valid domain bucket
				if (strlen($bucket) > 63 ||
					preg_match("/[^a-z0-9\.-]/", $bucket) > 0 ||
					strpos($bucket, '-.') !== false ||
					strpos($bucket, '..') !== false ||
					!preg_match("/^[0-9a-z]/", $bucket) ||
					!preg_match("/[0-9a-z]$/", $bucket)
				) {
					// Invalid domain
					$host = "s3.amazonaws.com";
					
					if ($bucket) {
						$uri = "/".$bucket.$uri;
					}
					
					$resource = $uri;
				} else {
					// Valid domain
					$host = $bucket."."."s3.amazonaws.com";
					$resource = "/".$bucket.$uri;
				}
			} else {
				$host = "s3.amazonaws.com";
				$resource = $uri;
			}
			
			if (is_array($params) && count($params)) {
				$query = (substr($uri, -1) !== "?") ? "?" : "&";
				
				// Build out the GET vars
				foreach ($params as $key => $val) {
					if (!$val) {
						$query .= "$key&";
					} else {
						$query .= "$key=".rawurlencode($val)."&";
					}
				}
				
				// Chop off the last &
				$query = substr($query, 0, -1);
				$uri .= $query;
				
				if (array_key_exists("acl", $params) ||
					array_key_exists("location", $params) ||
					array_key_exists("torrent", $params) ||
					array_key_exists("website", $params) ||
					array_key_exists("logging", $params)
				) {
					$resource .= $query;
				}
			}
			
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, "http://".($host ? $host : "s3.amazonaws.com").$uri);
			
			// Build out headers
			$date = gmdate("D, d M Y H:i:s T");
			$headers = ["Date: $date"];
			
			if ($host) {
				$headers[] = "Host: $host";
			}
			
			// Amazon headers
			$amazon_header_signature = [];
			
			foreach ($amazon_headers as $key => $val) {
				if ($val) {
					$headers[] = "$key: $val";
				}
				
				// Signature building
				$amazon_header_signature[] = strtolower(trim($key)).":".trim(str_replace(["\r", "\n"], " ", $val));
			}
			
			// Amazon wants this for some reason, I believe
			if (!isset($request_headers["Content-MD5"])) {
				$request_headers["Content-MD5"] = "";
			}
			
			if (!isset($request_headers["Content-Type"])) {
				$request_headers["Content-Type"] = "";
			}
			
			// Generic headers
			foreach ($request_headers as $key => $val) {
				if ($val) {
					$headers[] = "$key: $val";
				}
			}
			
			// Sort Amazon Headers for signature
			if (is_array($amazon_header_signature) && count($amazon_header_signature)) {
				sort($amazon_header_signature, SORT_STRING);
				$amazon_header_signature = implode("\n", $amazon_header_signature)."\n";
			} else {
				$amazon_header_signature = "";
			}
			
			if ($verb == "GET" && substr($uri, 0, 1) == "?") {
				$signable_resource = $resource.ltrim($uri, "/");
			} else {
				$signable_resource = $resource;
			}
			
			$string_to_sign = $verb."\n".$request_headers["Content-MD5"]."\n".$request_headers["Content-Type"]."\n".
				$date."\n".$amazon_header_signature.$signable_resource;
			
			$headers[] = "Authorization: AWS ".$this->Settings["amazon"]["key"].":".
				$this->hash($this->Settings["amazon"]["secret"], $string_to_sign);
			
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			
			// Different methods
			$file_pointer = false;
			
			if ($verb == "PUT") {
				if ($file) {
					curl_setopt($curl, CURLOPT_PUT, true);
					curl_setopt($curl, CURLOPT_INFILESIZE, filesize($file));
					$file_pointer = fopen($file, "r");
					curl_setopt($curl, CURLOPT_INFILE, $file_pointer);
				} elseif ($data) {
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				} else {
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
				}
			} elseif ($verb == "POST") {
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			} elseif ($verb == "HEAD") {
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "HEAD");
				curl_setopt($curl, CURLOPT_NOBODY, true);
			} elseif ($verb == "DELETE") {
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
			}
			
			$response = curl_exec($curl);
			
			if ($file_pointer) {
				fclose($file_pointer);
			}
			
			$this->HTTPResponseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			
			return $response;
		}
		
		// Implements Provider::copyFile
		function copyFile(string $source_container, string $source_pointer, string $destination_container,
						  string $destination_pointer, bool $public = false): ?string {
			$this->callAmazon("PUT", $destination_container, $destination_pointer, [], ["Content-Length" => "0"], [
				"x-amz-acl" => ($public ? "public-read" : "private"),
				"x-amz-copy-source" => "/".$source_container."/".rawurlencode($source_pointer)
			]);
			
			if ($this->HTTPResponseCode != "200") {
				return null;
			}
			
			return "//s3.amazonaws.com/$destination_container/$destination_pointer";
		}
		
		// Implements Provider::createContainer
		function createContainer(string $name, bool $public = false): ?bool {
			$response = $this->callAmazon("PUT", $name, "", [], [], ["x-amz-acl" => "private"]);
			
			// A response is a bad thing, none means success
			if (!$response) {
				// Set the policy to be public
				if ($public) {
					$data = [
						"Version" => date("Y-m-d"),
						"Statement" => [[
							"Sid" => "AllowPublicRead",
							"Effect" => "Allow",
							"Principle" => ["AWS" => "*"],
							"Action" => ["s3:GetObject"],
							"Resource" => ["arn:aws:s3:::$name/*"]
						]]
					];
					$this->callAmazon("PUT", "$name?policy", json_encode($data));
				}
				
				return true;
			}
			
			$this->setError($response);
			
			return false;
		}
		
		// Implements Provider::createFile
		function createFile(string $contents, string $container, string $pointer, bool $public = false,
							string $type = "text/plain"): ?string {
			$contents = strlen($contents) ? $contents : " ";
			
			$response = $this->callAmazon("PUT", $container, $pointer, [], [
				"Content-Type" => $type,
				"Content-Length" => strlen($contents)
			], ["x-amz-acl" => ($public ? "public-read" : "private")], $contents);
			
			// A response means failure
			if (!$response) {
				return "//s3.amazonaws.com/$container/$pointer";
			}
			
			$this->setError($response);
			
			return null;
		}
		
		// Implements Provider::deleteContainer
		function deleteContainer(string $container): ?bool {
			$response = $this->callAmazon("DELETE", $container);
			
			// No response means success
			if (!$response) {
				return true;
			}
			
			$this->setError($response);
			
			return false;
		}
		
		// Implements Provider::deleteFile
		function deleteFile(string $container, string $pointer): ?bool {
			$this->callAmazon("DELETE", $container, $pointer);
			
			if ($this->HTTPResponseCode != "204") {
				return false;
			}
			
			return true;
		}
		
		// Implements Provider::getAuthenticatedFileURL
		function getAuthenticatedFileURL(string $container, string $pointer, int $expires): ?string {
			$expires += time();
			$pointer = str_replace(['%2F', '%2B'], ['/', '+'], rawurlencode($pointer));
			$key = $this->Settings["amazon"]["key"];
			$secret = $this->Settings["amazon"]["secret"];
			
			$signature = urlencode($this->hash($secret, "GET\n\n\n$expires\n/$container/$pointer"));
			
			return "//s3.amazonaws.com/$container/$pointer?AWSAccessKeyId=$key&Expires=$expires&Signature=$signature";
		}
		
		// Implements Provider::getContainer
		function getContainer(string $container, bool $simple = false): ?array {
			$flat = [];
			$continue = true;
			$marker = "";
			
			while ($continue) {
				$response = $this->callAmazon("GET", $container, "", ["marker" => $marker]);
				$xml = simplexml_load_string($response);
				
				if (isset($xml->Name)) {
					foreach ($xml->Contents as $item) {
						if ($simple) {
							$flat[] = [
								"name" => (string) $item->Key,
								"path" => (string) $item->Key,
								"size" => (int) $item->Size
							];
						} else {
							$flat[(string) $item->Key] = [
								"name" => (string) $item->Key,
								"path" => (string) $item->Key,
								"updated_at" => date("Y-m-d H:i:s", strtotime($item->LastModified)),
								"etag" => (string) $item->ETag,
								"size" => (int) $item->Size,
								"owner" => [
									"name" => (string) $item->Owner->DisplayName,
									"id" => (string) $item->Owner->ID
								],
								"storage_class" => (string) $item->StorageClass
							];
						}
					}
					
					$continue = false;
					
					// Multi-page
					if (!empty($item) && $xml->IsTruncated == "true") {
						$continue = true;
						$marker = (string) $item->Key;
					}
				} else {
					$this->setError($response);
					trigger_error('BigTree\CloudStorage\Amazon::getContainer call failed.', E_USER_WARNING);
					
					return [];
				}
			}
			
			return $simple ? $flat : ["tree" => $this->getContainerTree($flat), "flat" => $flat];
		}
		
		// Implements Provider::getFile
		function getFile(string $container, string $pointer): ?string {
			$response = $this->callAmazon("GET", $container, $pointer);
			
			if ($this->HTTPResponseCode != "200") {
				return null;
			}
			
			return $response;
		}
		
		// Hashes data for HMAC auth
		protected function hash(string $secret, string $string): string {
			if (extension_loaded("hash")) {
				return base64_encode(hash_hmac('sha1', $string, $secret, true));
			}
			
			return base64_encode(pack('H*', sha1((str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))).pack('H*', sha1((str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))).$string)))));
		}
		
		// Implements Provider::listContainers
		function listContainers(): ?array {
			$containers = [];
			$response = $this->callAmazon();
			
			$xml = simplexml_load_string($response);
			if (isset($xml->Buckets)) {
				foreach ($xml->Buckets->Bucket as $bucket) {
					$containers[] = [
						"name" => (string) $bucket->Name,
						"created_at" => date("Y-m-d H:i:s", strtotime($bucket->CreationDate))
					];
				}
				
				return $containers;
			} else {
				$this->setError($response);
				
				return null;
			}
		}
		
		// Implements Provider::makeFilePublic
		function makeFilePublic(string $container, string $pointer): ?string {
			// Get existing ACL
			$xml = $this->callAmazon("GET", $container, $pointer, ["acl" => ""]);
			
			// Remove XML opening tags
			$xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>
', '', $xml);
			
			// Add in our global read ACL
			$xml = str_replace('</AccessControlList>',
							   '<Grant>
									<Grantee xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="Group">
										<URI>http://acs.amazonaws.com/groups/global/AllUsers</URI>
									</Grantee>
									<Permission>READ</Permission>
								</Grant></AccessControlList>',
							   $xml);
			
			// Send back the ACL
			$this->callAmazon("PUT", $container, $pointer, ["acl" => ""], ["Content-Type" => "text/xml"], [], $xml);
			
			return "//s3.amazonaws.com/$container/$pointer";
		}
		
		// Parses an Amazon response for the error message and adds to $this->Errors
		protected function setError(string $xml): void {
			$xml = simplexml_load_string($xml);
			$this->Errors[] = ["message" => (string) $xml->Message, "code" => (string) $xml->Code];
		}
		
		// Implements Provider::uploadFile
		function uploadFile(string $file, string $container, ?string $pointer = null, bool $public = false): ?string {
			// No target destination, just use root folder w/ file name
			if (!$pointer) {
				$path_info = pathinfo($file);
				$pointer = $path_info["basename"];
			}
			
			// Get MIME type
			$content_type = $this->getContentType($file);
			
			$response = $this->callAmazon("PUT", $container, $pointer, [], [
				"Content-Type" => $content_type,
				"Content-Length" => filesize($file)
			], ["x-amz-acl" => ($public ? "public-read" : "private")], false, $file);
			
			// No response means success
			if (!$response) {
				return "//s3.amazonaws.com/$container/$pointer";
			}
			
			$this->setError($response);
			
			return null;
		}
		
	}