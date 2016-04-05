<?php
	/*
		Class: BigTree\CloudStorage\Google
			A cloud storage interface class for Google Cloud Storage.
			See BigTree\CloudStorage\Provider for method definitions.
	*/

	namespace BigTree\CloudStorage;

	use BigTree;

	class Google extends Provider {

		public $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		public $EndpointURL = "https://www.googleapis.com/storage/v1/";
		public $OAuthVersion = "1.0";
		public $RequestType = "header";
		public $Scope = "https://www.googleapis.com/auth/devstorage.full_control";
		public $TokenURL = "https://accounts.google.com/o/oauth2/token";

		// Implements Provider::copyFile
		function copyFile($source_container,$source_pointer,$destination_container,$destination_pointer,$public = false) {
			$response = $this->call("b/$source_container/o/".rawurlencode($source_pointer)."/copyTo/b/$destination_container/o/".rawurlencode($destination_pointer),"{}","POST");

			if (isset($response->id)) {
				// Set the access control level if it's publicly accessible
				if ($public) {
					$this->call("b/$destination_container/o/".rawurlencode($destination_pointer)."/acl",json_encode(array("entity" => "allUsers","role" => "READER")),"POST");
				}

				return "//storage.googleapis.com/$destination_container/$destination_pointer";
			}

			return false;
		}

		// Implements Provider::createContainer
		function createContainer($name,$public = false) {
			$request = array("name" => $name);

			if ($public) {
				$request["defaultObjectAcl"] = array(
					array("role" => "READER","entity" => "allAuthenticatedUsers"),
					array("role" => "READER","entity" => "allUsers")
				);
			}

			$response = $this->call("b?project=".$this->Settings["project"],json_encode($request),"POST");

			return isset($response->id) ? true : false;
		}

		// Implements Provider::createFile
		function createFile($contents,$container,$pointer,$public = false,$type = "text/plain") {
			$response = json_decode(BigTree::cURL("https://www.googleapis.com/upload/storage/v1/b/$container/o?name=$pointer&uploadType=media",$contents,array(
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => array(
					"Content-Type: $type",
					"Content-Length: ".strlen($contents),
					"Authorization: Bearer ".$this->Settings["token"]
				)
			)));

			// Success
			if (isset($response->id)) {
				// Set the access control level if it's publicly accessible
				if ($public) {
					$this->call("b/$container/o/".rawurlencode($pointer)."/acl",json_encode(array("entity" => "allUsers","role" => "READER")),"POST");
				}

				return "//storage.googleapis.com/$container/$pointer";
			}

			foreach ($response->error->errors as $error) {
				$this->Errors[] = $error;
			}

			return false;
		}

		// Implements Provider::deleteContainer
		function deleteContainer($container) {
			$error_count = count($this->Errors);

			$this->call("b/$container",false,"DELETE");

			// The call stack will register a new error if it fails
			if (count($this->Errors) > $error_count) {
				return false;
			}

			return true;
		}

		// Implements Provider::deleteFile
		function deleteFile($container,$pointer) {
			$error_count = count($this->Errors);

			$this->call("b/$container/o/".rawurlencode($pointer),false,"DELETE");

			// The call stack will register a new error if it fails
			if (count($this->Errors) > $error_count) {
				return false;
			}

			return true;
		}

		// Implements Provider::getAuthenticatedFileURL
		function getAuthenticatedFileURL($container,$pointer,$expires) {
			$expires += time();

			if (!function_exists('openssl_x509_read')) {
				trigger_error("PHP's OpenSSL extension is required to use authenticated URLs with Google Cloud Storage.",E_USER_ERROR);
			}

			if (!$this->Settings["private_key"] || !$this->Settings["certificate_email"]) {
				trigger_error("You must upload your Google Cloud Storage private key and set your Certificate Email Address to use authenticated URLs.",E_USER_ERROR);
			}

			// Google's default password for these is "notasecret"
			$certificates = array();
			if (!openssl_pkcs12_read(file_get_contents($this->Settings["private_key"]),$certificates,"notasecret")) {
				trigger_error("Unable to parse Google Cloud Storage private key file:".openssl_error_string(),E_USER_ERROR);
			}

			// Sign the string
			$private_key = openssl_pkey_get_private($certificates["pkey"]);
			openssl_sign("GET\n\n\n$expires\n/$container/".str_replace(array("+","%2F"),array("%20","/"),urlencode($pointer)),$signature,$private_key,"sha256");
			$signature = urlencode(base64_encode($signature));
			$access_id = $this->Settings["certificate_email"];

			return "//storage.googleapis.com/$container/$pointer?GoogleAccessId=$access_id&Expires=$expires&Signature=$signature";
		}

		// Implements Provider::getContainer
		function getContainer($container,$simple = false) {
			$flat = array();
			$response = $this->call("b/$container/o");

			if (isset($response->kind) && $response->kind == "storage#objects") {
				if (is_array($response->items)) {
					foreach ($response->items as $item) {
						if ($simple) {
							$flat[] = array(
								"name" => (string) $item->name,
								"path" => (string) $item->name,
								"size" => (int) $item->size
							);
						} else {
							$flat[(string) $item->name] = array(
								"name" => (string) $item->name,
								"path" => (string) $item->name,
								"updated_at" => date("Y-m-d H:i:s",strtotime($item->updated)),
								"etag" => (string) $item->etag,
								"size" => (int) $item->size,
								"owner" => array(
									"name" => (string) $item->owner->entity,
									"id" => (string) $item->owner->entityId
								)
							);
						}
					}
				}
			} else {
				return false;
			}

			return $simple ? $flat : array("tree" => $this->getContainerTree($flat), "flat" => $flat);
		}

		// Implements Provider::getfile
		function getFile($container,$pointer) {
			return BigTree::cURL("https://storage.googleapis.com/$container/$pointer",false,array(
				CURLOPT_HTTPHEADER => array("Authorization: Bearer ".$this->Settings["token"])
			));
		}

		// Implements Provider::listContainers
		function listContainers() {
			$containers = array();
			$response = $this->call("b",array("project" => $this->Settings["project"]));

			if (isset($response->kind) && $response->kind == "storage#buckets") {
				if (is_array($response->items)) {
					foreach ($response->items as $item) {
						$containers[] = array(
							"name" => (string)$item->name,
							"created_at" => date("Y-m-d H:i:s",strtotime($item->timeCreated)),
							"location" => (string)$item->location,
							"storage_class" => (string)$item->storageClass
						);
					}
				}
			} else {
				return false;
			}

			return $containers;
		}

		// Implements Provider::makeFilePublic
		function makeFilePublic($container,$pointer) {
			$response = $this->call("b/$container/o/".rawurlencode($pointer)."/acl",json_encode(array("entity" => "allUsers","role" => "READER")),"POST");

			if ($response) {
				return "//storage.googleapis.com/$container/".str_replace("%2F","/",rawurlencode($pointer));
			}

			return false;
		}

		// Implements Provider::uploadFile
		function uploadFile($file,$container,$pointer = false,$public = false) {
			// No target destination, just use root folder w/ file name
			if (!$pointer) {
				$path_info = BigTree::pathInfo($file);
				$pointer = $path_info["basename"];
			}

			// Get MIME type
			$content_type = $this->getContentType($file);

			// Open file pointer for cURL to upload
			$file_pointer = fopen($file,"r");

			$response = json_decode(BigTree::cURL("https://www.googleapis.com/upload/storage/v1/b/$container/o?name=$pointer&uploadType=media",false,array(
				CURLOPT_INFILE => $file_pointer,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => array(
					"Content-Type: $content_type",
					"Content-Length: ".filesize($file),
					"Authorization: Bearer ".$this->Settings["token"]
				)
			)));

			fclose($file_pointer);

			if (isset($response->id)) {
				// Set the access control level if it's publicly accessible
				if ($public) {
					$this->call("b/$container/o/".rawurlencode($pointer)."/acl",json_encode(array("entity" => "allUsers","role" => "READER")),"POST");
				}

				return "//storage.googleapis.com/$container/$pointer";
			} else {
				foreach ($response->error->errors as $error) {
					$this->Errors[] = $error;
				}

				return false;
			}
		}

	}
