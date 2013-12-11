<?
	/*
		Class: BigTreeCloudStorage
			A cloud storage interface class that provides service agnostic calls on top of various cloud storage platforms.
	*/

	class BigTreeCloudStorage {

		/*
			Constructor:
				Retrieves the current desired service and settings.
		*/
		
		function __construct() {
			global $cms;
			$admin = new BigTreeAdmin;
			$settings = $cms->getSetting("bigtree-internal-cloud-storage");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($settings) || !$settings["service"]) {
				$this->Service = "offline";
				$admin->createSetting(array(
					"id" => "bigtree-internal-cloud-storage",
					"encrypted" => "on",
					"system" => "on"
				));
				$admin->updateSettingValue("bigtree-internal-cloud-storage",array("service" => "offline"));
			} else {
				$this->Service = $settings["service"];
				$this->Settings = $settings;
			}
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

		private function _hash($secret,$string) {
			if (extension_loaded("hash")) {
				return base64_encode(hash_hmac('sha1',$string,$secret,true));
			}
			return base64_encode(pack('H*',sha1((str_pad($secret,64,chr(0x00)) ^ (str_repeat(chr(0x5c),64))).pack('H*',sha1((str_pad($secret,64,chr(0x00)) ^ (str_repeat(chr(0x36), 64))).$string)))));
		}

		/*
			Function: createContainer
				Creates a new container/bucket.

			Parameters:
				name - Container name (keep in mind this must be unique among all other containers)
				access - Access control level: "private" for no outside access (default), "read" for public read, "write" for public read/write
			
			Returns:
				true if successful.
		*/

		function createContainer($name,$access = "private") {
			// Amazon S3
			if ($this->Service == "amazon") {
				// Get the Amazon code for the access level
				$access_levels = array("private" => "private","read" => "public-read","write" => "public-read-write");
				$acl = $access_levels[$access];
				if (!$acl) {
					return false;
				}

				$response = $this->callAmazonS3("PUT",$name,"",array(),array(),array("x-amz-acl" => $acl));
				if (!$response) {
					return true;
				}
				$this->setAmazonError($response);
				return false;
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {

			// Google Cloud Storage
			} elseif ($this->Service == "google") {

			} else {
				return false;
			}
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

		function deleteContainer($container) {
			// Amazon S3
			if ($this->Service == "amazon") {
				$response = $this->callAmazonS3("DELETE",$container);
				if (!$response) {
					return true;
				}
				$this->setAmazonError($response);
				return false;
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {

			// Google Cloud Storage
			} elseif ($this->Service == "google") {

			} else {
				return false;
			}
		}

		/*
			Function: getContainer
				Lists the contents of a container/bucket.

			Parameters:
				container - The name of the container.

			Returns:
				An array of the contents of the container.
		*/

		function getContainer($container) {
			$tree = array("folders" => array(),"files" => array());
			$flat = array();
			
			// Amazon S3
			if ($this->Service == "amazon") {
				$xml = simplexml_load_string($this->callAmazonS3("GET",$container));
				foreach ($xml->Contents as $item) {
					$flat[] = $raw_item = array(
						"name" => (string)$item->Key,
						"path" => (string)$item->Key,
						"updated_at" => date("Y-m-d H:i:s",strtotime($item->LastModified)),
						"etag" => (string)$item->ETag,
						"size" => (int)$item->Size,
						"owner" => array(
							"name" => (string)$item->Owner->DisplayName,
							"id" => (string)$item->Owner->ID
						),
						"storage_class" => (string)$item->StorageClass
					);
					$keys = explode("/",$raw_item["name"]);
					// We're going to use by reference vars to figure out which folder to place this in
					if (count($keys) > 1) {
						$folder = &$tree;
						for ($i = 0; $i < count($keys); $i++) {
							// Last part of the key and also has a . so we know it's actually a file
							if ($i == count($keys) - 1 && strpos($keys[$i],".") !== false) {
								$raw_item["name"] = $keys[$i];
								$folder["files"][] = $raw_item;
							} else {
								if ($keys[$i]) {
									if (!isset($folder["folders"][$keys[$i]])) {
										$folder["folders"][$keys[$i]] = array("folders" => array(),"files" => array());
									}
									$folder = &$folder["folders"][$keys[$i]];
								}
							}
						}
					} else {
						$tree["files"][] = $raw_item;
					}
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {

			// Google Cloud Storage
			} elseif ($this->Service == "google") {

			} else {
				return false;
			}

			return array("flat" => $flat,"tree" => $tree);
		}

		/*
			Function: listContainers
				Lists containers/buckets that are available in this cloud account.

			Returns:
				An array of container names.
		*/

		function listContainers() {
			$containers = array();
			// Amazon S3
			if ($this->Service == "amazon") {
				$xml = simplexml_load_string($this->callAmazonS3());
				foreach ($xml->Buckets->Bucket as $bucket) {
					$containers[] = array(
						"name" => (string)$bucket->Name,
						"created_at" => date("Y-m-d H:i:s",strtotime($bucket->CreationDate))
					);
				}
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {

			// Google Cloud Storage
			} elseif ($this->Service == "google") {

			} else {
				return false;
			}

			return $containers;
		}

		/*
			Function: updateContainerAccessLevel
				Updates the access level of a container.
				For Amazon S3, the container will be created if it doesn't exist.

			Parameters:
				container - Container name (keep in mind this must be unique among all other containers)
				access - Access control level: "private" for no outside access (default), "read" for public read, "write" for public read/write
			
			Returns:
				true if successful.
		*/

		function updateContainerAccessLevel($container,$level) {
			// Amazon S3
			if ($this->Service == "amazon") {
				return $this->createContainer($container,$level);
			// Rackspace Cloud Files
			} elseif ($this->Service == "rackspace") {

			// Google Cloud Storage
			} elseif ($this->Service == "google") {

			} else {
				return false;
			}
		}

		/*
			Function: callAmazonS3
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
	
		function callAmazonS3($verb = "GET",$bucket = "",$uri = "",$params = array(),$request_headers = array(),$amazon_headers = array(),$data = false,$file = false) {
			$headers = array();
			$resource = "";
			$uri = $uri ? "/".str_replace("%2F","/",rawurlencode($uri)) : "/";
			$host = false;

			if ($bucket) {
				// See if it's a valid domain bucket
				if (strlen($bucket) > 63 ||
					preg_match("/[^a-z0-9\.-]/", $bucket) > 0 ||
					strpos($bucket, '-.') !== false ||
					strpos($bucket, '..') !== false ||
					!preg_match("/^[0-9a-z]/", $bucket) ||
					!preg_match("/[0-9a-z]$/", $bucket)) {
					// Invalid domain
					$host = "s3.amazonaws.com";
					if ($bucket) {
						$uri = "/".$bucket.$uri;
						$bucket = "";
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
		
			$query = "";
			if (count($params)) {
				$query = (substr($uri,-1) !== "?") ? "?" : "&";
				// Build out the GET vars
				foreach ($params as $key => $val) {
					if (!$val) {
						$query .= "$key&";
					} else {
						$query .= "$key=".rawurlencode($val)."&";
					}
				}
				// Chop off the last &
				$query = substr($query,0,-1);
				$uri .= $query;

				if (array_key_exists("acl",$params) ||
					array_key_exists("location",$params) ||
					array_key_exists("torrent",$params) ||
					array_key_exists("website",$params) ||
					array_key_exists("logging",$params)) {
					$resource .= $query;
				}
			}

			$curl = curl_init();
			curl_setopt($curl,CURLOPT_URL,"http://".($host ? $host : "s3.amazonaws.com").$uri);
			
			// Build out headers
			$date = gmdate("D, d M Y H:i:s T");
			$headers = array("Date: $date");
			if ($host) {
				$headers[] = "Host: $host";
			}
			// Amazon headers
			$amazon_header_signature = array();
			foreach ($amazon_headers as $key => $val) {
				if ($val) {
					$headers[] = "$key: $val";
				}
				// Signature building
				$amazon_header_signature[] = strtolower(trim($key)).":".trim(str_replace(array("\r","\n")," ",$val));
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
			if (count($amazon_header_signature)) {
				sort($amazon_header_signature,SORT_STRING);
				$amazon_header_signature = implode("\n",$amazon_header_signature)."\n";
			} else {
				$amazon_header_signature = "";
			}	
			
			$headers[] = "Authorization: AWS ".$this->AmazonKey.":".$this->_hash(
				$this->AmazonSecret,
				$verb."\n".$request_headers["Content-MD5"]."\n".$request_headers["Content-Type"]."\n".$date."\n".$amazon_header_signature.$resource
			);

			curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
			curl_setopt($curl,CURLOPT_HEADER,false);
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);

			// Different methods
			$file_pointer = false;
			if ($verb == "PUT") {
				curl_setopt($curl,CURLOPT_PUT,true);
				if ($file) {
					curl_setopt($curl,CURLOPT_INFILESIZE,filesize($file));
					$file_pointer = fopen($file,"r");
					curl_setopt($curl,CURLOPT_INFILE,$file_pointer);
				} elseif ($data) {
					curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
				}
			} elseif ($verb == "POST") {
				curl_setopt($curl,CURLOPT_POST,true);
				curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
			} elseif ($verb == "HEAD") {
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"HEAD");
				curl_setopt($curl,CURLOPT_NOBODY,true);
			} elseif ($verb == "DELETE") {
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"DELETE");
			}

			$response = curl_exec($curl);
			if ($file_pointer) {
				fclose($file_pointer);
			}
			return $response;
		}

		/*
			Function: setAmazonError
				Parses an Amazon response for the error message and sets $this->Error
		*/

		private function setAmazonError($xml) {
			$xml = simplexml_load_string($xml);
			$this->Error = (string)$xml->Message;
			$this->ErrorCode = (string)$xml->Code;
		}
	}
?>