<?php
	/*
		Class: BigTree\CloudStorage\Amazon
			A cloud storage interface class for Amazon S3.
			See BigTree\CloudStorage\Provider for method definitions.
	*/
	
	namespace BigTree\CloudStorage;
	
	use Aws\CloudFront\CloudFrontClient;
	use AWS\S3\S3Client;
	use Exception;
	
	class Amazon extends Provider
	{
		
		public $AvailableRegions = [
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
		public $CloudFrontDistribution;
		public $CloudFrontDomain;
		public $CloudFrontSSL;
		public $HTTPResponseCode = false;
		public $Key;
		public $Region;
		public $Secret;
		
		/** @var CloudFrontClient */
		public $CloudFrontClient;
		/** @var S3Client */
		public $S3Client;
		
		public function __construct()
		{
			parent::__construct();
			
			$this->Active = &$this->Settings["amazon"]["active"];
			$this->CloudFrontDistribution = &$this->Settings["amazon"]["cloudfront_distribution"];
			$this->CloudFrontDomain = &$this->Settings["amazon"]["cloudfront_domain"];
			$this->CloudFrontSSL = &$this->Settings["amazon"]["cloudfront_ssl"];
			$this->Key = &$this->Settings["amazon"]["key"];
			$this->Region = &$this->Settings["amazon"]["region"];
			$this->Secret = &$this->Settings["amazon"]["secret"];
			
			$this->setup();
		}
		
		// Implements Provider::copyFile
		public function copyFile(string $source_container, string $source_pointer, string $destination_container,
						  string $destination_pointer, bool $public = false): ?string
		{
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
				
				return null;
			}
		}
		
		// Implements Provider::createContainer
		public function createContainer(string $name, bool $public = false): ?bool
		{
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
		}
		
		// Implements Provider::createFile
		public function createFile(string $contents, string $container, string $pointer, bool $public = false,
							string $type = "text/plain"): ?string
		{
			$contents = strlen($contents) ? $contents : " ";
			$extension = strtolower(pathinfo($pointer, PATHINFO_EXTENSION));
			$type = isset($this->MimeExtensions[$extension]) ? $this->MimeExtensions[$extension] : "text/plain";
			
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
				
				return null;
			}
		}
		
		// Implements Provider::deleteContainer
		public function deleteContainer(string $container): ?bool
		{
			try {
				$this->S3Client->deleteBucket(["Bucket" => $container]);
				
				return true;
			} catch (Exception $e) {
				$this->Errors[] = $e->getMessage();
				
				return false;
			}
		}
		
		// Implements Provider::deleteFile
		public function deleteFile(string $container, string $pointer): ?bool
		{
			try {
				$this->S3Client->deleteObject(["Bucket" => $container, "Key" => $pointer]);
				
				return true;
			} catch (Exception $e) {
				$this->Errors[] = $e->getMessage();
				
				return false;
			}
		}
		
		// Implements Provider::getAuthenticatedFileURL
		public function getAuthenticatedFileURL(string $container, string $pointer, int $expires): ?string
		{
			$pointer = str_replace(['%2F', '%2B'], ['/', '+'], rawurlencode($pointer));
			
			return "//$container.s3.amazonaws.com/".$pointer."?AWSAccessKeyId=".$this->Key."&Expires=$expires&Signature=".urlencode($this->hash($this->Secret, "GET\n\n\n$expires\n/$container/$pointer"));
		}
		
		// Implements Provider::getContainer
		public function getContainer(string $container, bool $simple = false): ?array
		{
			$continue = true;
			$marker = "";
			$contents = [];
			
			while ($continue) {
				try {
					$response = $this->S3Client->listObjects(["Bucket" => $container, "Marker" => $marker]);
					$x = 0;
					$item = null;
					
					foreach ($response["Contents"] as $item) {
						$x++;
						
						if ($x == 1 && $marker) {
							continue;
						}
						
						if ($simple) {
							$contents[] = [
								"name" => $item["Key"],
								"path" => $item["Key"],
								"size" => $item["Size"]
							];
						} else {
							$contents[$item["Key"]] = [
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
					
					return null;
				}
			}
			
			return $contents;
		}
		
		// Implements Provider::getFile
		public function getFile(string $container, string $pointer): ?string
		{
			try {
				$response = $this->S3Client->getObject(["Bucket" => $container, "Key" => $pointer]);
				
				return $response["Body"];
			} catch (Exception $e) {
				$this->Errors[] = $e->getMessage();
				
				return null;
			}
		}
		
		// Hashes data for HMAC auth
		protected function hash(string $secret, string $string): string
		{
			if (extension_loaded("hash")) {
				return base64_encode(hash_hmac('sha1', $string, $secret, true));
			}
			
			return base64_encode(pack('H*', sha1((str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))).pack('H*', sha1((str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))).$string)))));
		}
		
		// Implements Provider::invalidateCache
		public function invalidateCache($pointer): bool
		{
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
		
		// Implements Provider::listContainers
		public function listContainers(): ?array
		{
			$containers = [];
			
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
				
				return null;
			}
			
			return $containers;
		}
		
		// Implements Provider::makeFilePublic
		public function makeFilePublic(string $container, string $pointer): bool
		{
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
		}
		
		// Sets up the environment
		public function setup()
		{
			$ca_cert = file_exists(SERVER_ROOT."cache/bigtree-ca-cert.pem") ? SERVER_ROOT."cache/bigtree-ca-cert.pem" : SERVER_ROOT."core/cacert.pem";
			
			$this->S3Client = new S3Client([
				"version" => "latest",
				"region" => $this->Region,
				"credentials" => [
					"key" => $this->Key,
					"secret" => $this->Secret
				],
				"http" => [
					"verify" => $ca_cert
				]
			]);
			
			$this->CloudFrontClient = new CloudFrontClient([
				"version" => "latest",
				"region" => $this->Region,
				"credentials" => [
					"key" => $this->Key,
					"secret" => $this->Secret
				],
				"http" => [
					"verify" => $ca_cert
				]
			]);
		}
		
		// Implements Provider::uploadFile
		public function uploadFile(string $file, string $container, ?string $pointer = null, bool $public = false): ?string
		{
			// Default the pointer to the name of the file if not provided.
			if (!$pointer) {
				$path_info = pathinfo($file);
				$pointer = $path_info["basename"];
			} else {
				$path_info = pathinfo($pointer);
			}
			
			// Get destination mime type
			$content_type = isset($this->MimeExtensions[strtolower($path_info["extension"])]) ? $this->MimeExtensions[strtolower($path_info["extension"])] : "application/octet-stream";
			
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
				
				return null;
			}
		}
		
	}