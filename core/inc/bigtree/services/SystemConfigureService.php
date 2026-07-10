<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Upload;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use BigTree;

	/**
	 * Exposes the Developer "Configure" section to the SPA. Every area is backed
	 * by a `bigtree-internal-*` setting (or, for media-presets / file-metadata, a
	 * JSONDB "config" row) — none of which are reachable via the public /settings
	 * endpoint by design, so we expose them through a dedicated route family
	 * gated to level:2.
	 *
	 * Areas:
	 *  - email           → bigtree-internal-email-service
	 *  - geocoding       → bigtree-internal-geocoding-service
	 *  - cloud-storage   → bigtree-internal-cloud-storage + bigtree-internal-storage
	 *  - payment-gateway → bigtree-internal-payment-gateway
	 *  - analytics       → bigtree-internal-google-analytics-4
	 *  - services        → bigtree-internal-{service} (one per provider)
	 *  - media-presets   → JSONDB("config", "media-settings")
	 *  - file-metadata   → JSONDB("config", "file-metadata")
	 *
	 * OAuth handshakes for the third-party "services" and Google Cloud Storage
	 * are brokered through the SPA-aware flow in OAuthBrokerService (start →
	 * launch → callback), so reconnecting no longer deep-links to the legacy
	 * admin. Analytics uses a direct service-account upload (see uploadAnalyticsCredentials).
	 */
	class SystemConfigureService {

		// — email —

		public function getEmail(Request $request) {
			$settings = BigTreeCMS::getSetting("bigtree-internal-email-service") ?: [];

			return Response::ok([
				"service" => (string)($settings["service"] ?? "local"),
				"settings" => is_array($settings["settings"] ?? null) ? $settings["settings"] : [],
			]);
		}

		public function updateEmail(Request $request) {
			$body = $request->body;
			$service = (string)($body["service"] ?? "local");
			$settings = is_array($body["settings"] ?? null) ? $body["settings"] : [];

			$next = [
				"service" => $service,
				"settings" => $settings,
			];

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-email-service", $next, true);

			return Response::ok($next);
		}

		// — geocoding —

		public function getGeocoding(Request $request) {
			$settings = BigTreeCMS::getSetting("bigtree-internal-geocoding-service") ?: [];

			return Response::ok([
				"service" => (string)($settings["service"] ?? ""),
				"google_key" => (string)($settings["google_key"] ?? ""),
				"bing_key" => (string)($settings["bing_key"] ?? ""),
				"mapquest_key" => (string)($settings["mapquest_key"] ?? ""),
			]);
		}

		public function updateGeocoding(Request $request) {
			$body = $request->body;
			$service = (string)($body["service"] ?? "");

			if (!in_array($service, ["", "google", "bing", "mapquest"], true)) {
				throw new BadRequestException("Unknown geocoding service", "invalid_service");
			}

			$next = [
				"service" => $service,
				"google_key" => (string)($body["google_key"] ?? ""),
				"bing_key" => (string)($body["bing_key"] ?? ""),
				"mapquest_key" => (string)($body["mapquest_key"] ?? ""),
			];

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-geocoding-service", $next, true);

			return Response::ok($next);
		}

		// — cloud-storage —

		public function getCloudStorage(Request $request) {
			$cloud = BigTreeCMS::getSetting("bigtree-internal-cloud-storage") ?: [];
			$storage = BigTreeCMS::getSetting("bigtree-internal-storage") ?: [];

			$providers = ["amazon", "rackspace", "google"];
			$out = [
				"default_service" => (string)($storage["Service"] ?? "local"),
				"default_container" => (string)($storage["Container"] ?? ""),
				"providers" => [],
			];

			foreach ($providers as $p) {
				$settings = is_array($cloud[$p] ?? null) ? $cloud[$p] : [];
				$out["providers"][$p] = [
					"active" => !empty($settings["active"]),
					"settings" => $this->maskCloudSecrets($p, $settings),
				];
			}

			return Response::ok($out);
		}

		public function updateCloudStorageProvider(Request $request) {
			$provider = $request->routeParam("provider");

			if (!in_array($provider, ["amazon", "rackspace", "google"], true)) {
				throw new NotFoundException("Unknown cloud provider", "unknown_provider");
			}

			$cloud = BigTreeCMS::getSetting("bigtree-internal-cloud-storage") ?: [];
			$settings = is_array($cloud[$provider] ?? null) ? $cloud[$provider] : [];

			foreach ($request->body as $key => $value) {
				// We never let the client flip `active` directly — that has to be
				// driven by a server-side credential test, which still lives in
				// the legacy admin. Document as a follow-up if/when we expose it.
				if ($key === "active") {
					continue;
				}

				$settings[$key] = $value;
			}

			$cloud[$provider] = $settings;
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-cloud-storage", $cloud, true);

			return Response::ok([
				"active" => !empty($settings["active"]),
				"settings" => $this->maskCloudSecrets($provider, $settings),
			]);
		}

		/**
		 * Store the Google Cloud Storage private key the legacy admin collected
		 * via /developer/cloud-storage/google/. Accepts the modern JSON key or
		 * the older .p12 file; the absolute path is recorded on the google
		 * provider settings so BigTreeCloudStorage can read it. The OAuth
		 * handshake that follows is handled separately (Workstream B).
		 */
		public function uploadGoogleStorageKey(Request $request) {
			$file = Upload::requireSingle($request, "file");

			$directory = SERVER_ROOT . "custom/";
			$extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
			$target = $directory . "google-cloud-private-key." . ($extension === "json" ? "json" : "p12");

			BigTree::moveFile($file["tmp_name"], $target);

			$cloud = BigTreeCMS::getSetting("bigtree-internal-cloud-storage") ?: [];
			$settings = is_array($cloud["google"] ?? null) ? $cloud["google"] : [];
			$settings["private_key"] = $target;
			$cloud["google"] = $settings;

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-cloud-storage", $cloud, true);

			return Response::ok([
				"active" => !empty($settings["active"]),
				"settings" => $this->maskCloudSecrets("google", $settings),
			]);
		}

		/**
		 * Set the default storage service and, for cloud services, provision the
		 * bucket/container. Mirrors the legacy set-container flow:
		 *
		 *   - An explicit container name is adopted as-is. For Amazon we also wire
		 *     the optional CloudFront distribution/domain/SSL and auto-detect the
		 *     bucket region; for Rackspace we CDN-enable the container.
		 *   - A blank container name auto-creates a unique bucket (up to 10 tries).
		 *
		 * Reuses BigTreeStorage / BigTreeCloudStorage so the credential handling,
		 * bucket creation, and file-cache population match the canonical CMS code.
		 */
		public function updateCloudStorageDefault(Request $request) {
			$service = $request->bodyString("service", "local", false);
			$container = $request->bodyString("container");

			if (!in_array($service, ["local", "amazon", "rackspace", "google"], true)) {
				throw new BadRequestException("Unknown storage service", "invalid_service");
			}

			$storage = new \BigTreeStorage();
			$storage->Settings->Service = $service;

			if ($service === "local") {
				$storage->saveSettings();

				return Response::ok([
					"default_service" => "local",
					"default_container" => (string)($storage->Settings->Container ?? ""),
				]);
			}

			$cloud = new \BigTreeCloudStorage($service);

			if ($container !== "") {
				$storage->Settings->Container = $container;
				$this->wireExistingContainer($cloud, $service, $container, $request);
			} else {
				$created = $this->autoCreateContainer($cloud, $service);

				if ($created === null) {
					$error = !empty($cloud->Errors) ? end($cloud->Errors) : "Failed to create container.";

					throw new BadRequestException(is_string($error) ? $error : "Failed to create container.", "container_create_failed");
				}

				$storage->Settings->Container = $created;
			}

			$cloud->saveSettings();
			$storage->saveSettings();

			// Best-effort: populate the file cache the Files browser reads. Heavy
			// for large buckets and non-fatal, so failures don't block the save.
			try {
				$resolved = $cloud->getContainer($storage->Settings->Container, true);

				if ($resolved !== false) {
					$cloud->resetCache($resolved);
				}
			} catch (\Throwable $e) {
				// Swallow — the container is set; the cache can be rebuilt later.
			}

			return Response::ok([
				"default_service" => $service,
				"default_container" => (string)($storage->Settings->Container ?? ""),
			]);
		}

		/** Adopt a user-supplied container, wiring CloudFront (Amazon) or CDN (Rackspace). */
		private function wireExistingContainer($cloud, $service, $container, Request $request) {
			if ($service === "rackspace") {
				BigTree::cURL($cloud->RackspaceCDNEndpoint . "/" . $container, "", [
					CURLOPT_PUT => true,
					CURLOPT_HTTPHEADER => [
						"X-Auth-Token: " . ($cloud->Settings["rackspace"]["token"] ?? ""),
						"X-Cdn-Enabled: true",
					],
				]);
			}

			if ($service === "amazon") {
				$cloud->Settings["amazon"]["cloudfront_distribution"] = $request->bodyString("cloudfront_distribution", "", false);
				$cloud->Settings["amazon"]["cloudfront_domain"] = $request->bodyString("cloudfront_domain", "", false);
				$cloud->Settings["amazon"]["cloudfront_ssl"] = $request->bodyString("cloudfront_ssl", "", false);
				$cloud->Settings["amazon"]["region"] = $cloud->getS3BucketRegion($container);
			}
		}

		/** Create a unique bucket, returning its name, or null after 10 failed tries. */
		private function autoCreateContainer($cloud, $service) {
			$attempts = 0;

			while ($attempts < 10) {
				$candidate = BigTreeCMS::urlify(uniqid("bigtree-container-", true));
				$attempts++;

				if ($service === "amazon" && $cloud->getS3BucketExists($candidate)) {
					continue;
				}

				if ($cloud->createContainer($candidate, true)) {
					return $candidate;
				}
			}

			return null;
		}

		/**
		 * Re-push the local file cache the Files browser reads against the live
		 * Amazon S3 bucket, mirroring the legacy /developer/cloud-storage/amazon/
		 * recache flow. Because a bucket can hold far more objects than a single
		 * request can page through before timing out, this works one S3 page at a
		 * time: the SPA calls it repeatedly, forwarding the `marker` it returns,
		 * until `complete` comes back true.
		 *
		 * On the first call (no marker) the existing cache is cleared so removed
		 * objects don't linger. Each subsequent call appends any not-yet-cached
		 * objects in that page. Matches core/admin/ajax/developer/amazon-cache.php.
		 */
		public function recacheAmazonStorage(Request $request) {
			$storage = new \BigTreeStorage();

			if (($storage->Settings->Service ?? "") !== "amazon") {
				throw new BadRequestException("Amazon S3 is not the active storage service.", "amazon_not_active");
			}

			$bucket = (string)($storage->Settings->Container ?? "");

			if ($bucket === "") {
				throw new BadRequestException("No Amazon S3 bucket is configured.", "no_bucket");
			}

			$marker = $request->bodyString("marker");

			if ($marker === "") {
				\SQL::delete("bigtree_caches", ["identifier" => "org.bigtreecms.cloudfiles"]);
			}

			$cloud = new \BigTreeCloudStorage("amazon");
			$page = $cloud->getS3BucketPage($bucket, $marker !== "" ? $marker : null);

			if ($page === false) {
				$error = !empty($cloud->Errors) ? end($cloud->Errors) : "Failed to read the S3 bucket.";

				throw new BadRequestException(is_string($error) ? $error : "Failed to read the S3 bucket.", "recache_failed");
			}

			$cached = 0;

			foreach ($page as $item) {
				if (!\SQL::exists("bigtree_caches", ["key" => $item["path"], "identifier" => "org.bigtreecms.cloudfiles"])) {
					\SQL::insert("bigtree_caches", [
						"identifier" => "org.bigtreecms.cloudfiles",
						"key" => $item["path"],
						"value" => [
							"name" => $item["name"],
							"path" => $item["path"],
							"size" => $item["size"],
						],
					]);

					$cached++;
				}
			}

			return Response::ok([
				"complete" => empty($cloud->NextPage),
				"marker" => $cloud->NextPage ?: null,
				"cached" => $cached,
				"processed" => count($page),
			]);
		}

		// — payment-gateway —

		public function getPaymentGateway(Request $request) {
			$gateway = BigTreeCMS::getSetting("bigtree-internal-payment-gateway") ?: [];
			$settings = is_array($gateway["settings"] ?? null) ? $gateway["settings"] : [];

			return Response::ok([
				"service" => (string)($gateway["service"] ?? ""),
				"settings" => $this->maskGatewaySecrets($settings),
			]);
		}

		public function updatePaymentGateway(Request $request) {
			$service = $request->bodyString("service", "", false);
			$incoming = is_array($request->body["settings"] ?? null) ? $request->body["settings"] : [];

			if (!in_array($service, ["", "authorize.net", "paypal", "paypal-rest", "payflow", "linkpoint"], true)) {
				throw new BadRequestException("Unknown payment gateway", "invalid_service");
			}

			$existing = BigTreeCMS::getSetting("bigtree-internal-payment-gateway") ?: [];
			$settings = is_array($existing["settings"] ?? null) ? $existing["settings"] : [];

			// Empty-string masked values mean "no change" — drop them so we keep
			// the existing secret.
			foreach ($incoming as $key => $value) {
				if ($value === "" && $this->isMaskedGatewayKey($key) && !empty($settings[$key])) {
					continue;
				}

				$settings[$key] = $value;
			}

			$next = [
				"service" => $service,
				"settings" => $settings,
			];

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-payment-gateway", $next, true);

			return Response::ok([
				"service" => $service,
				"settings" => $this->maskGatewaySecrets($settings),
			]);
		}

		/**
		 * Store the LinkPoint .pem certificate the legacy admin collected via
		 * /developer/payment-gateway/linkpoint/. The file lands in
		 * custom/certificates/ (same location the legacy flow used) and the
		 * resulting filename is recorded on the gateway settings.
		 */
		public function uploadLinkpointCertificate(Request $request) {
			$file = Upload::requireSingle($request, "file");

			$directory = SERVER_ROOT . "custom/certificates/";

			if (!is_dir($directory)) {
				@mkdir($directory, 0755, true);
			}

			$filename = BigTree::getAvailableFileName($directory, $file["name"]);
			BigTree::moveFile($file["tmp_name"], $directory . $filename);

			$existing = BigTreeCMS::getSetting("bigtree-internal-payment-gateway") ?: [];
			$settings = is_array($existing["settings"] ?? null) ? $existing["settings"] : [];
			$settings["linkpoint-certificate"] = $filename;

			$next = [
				"service" => (string)($existing["service"] ?? "linkpoint"),
				"settings" => $settings,
			];

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-payment-gateway", $next, true);

			return Response::ok([
				"service" => $next["service"],
				"settings" => $this->maskGatewaySecrets($settings),
			]);
		}

		// — analytics —

		public function getAnalytics(Request $request) {
			$settings = BigTreeCMS::getSetting("bigtree-internal-google-analytics-4") ?: [];
			$credentials = is_array($settings["credentials"] ?? null) ? $settings["credentials"] : [];

			return Response::ok([
				"verified" => !empty($settings["verified"]),
				"property_id" => (string)($settings["property_id"] ?? ""),
				"service_account" => (string)($credentials["client_email"] ?? ""),
			]);
		}

		public function disconnectAnalytics(Request $request) {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-google-analytics-4", [], true);

			return Response::noContent();
		}

		/**
		 * Accept the Google service-account JSON key the legacy admin used to
		 * collect via /developer/analytics/upload-client-file. We validate the
		 * shape, hand it to BigTreeGoogleAnalytics4::setCredentials (which stores
		 * it on the setting), and report the service-account email back so the
		 * SPA can prompt for a property ID next. Verification waits for the
		 * property ID — see updateAnalytics().
		 */
		public function uploadAnalyticsCredentials(Request $request) {
			$file = Upload::requireSingle($request, "file");
			$json = json_decode((string)@file_get_contents($file["tmp_name"]), true);

			if (!is_array($json) || empty($json["private_key"]) || empty($json["client_email"]) || empty($json["client_id"])) {
				throw new BadRequestException("That file is not a valid Google service-account key.", "invalid_credentials");
			}

			$analytics = new \BigTreeGoogleAnalytics4();
			$analytics->setCredentials($json);

			return $this->getAnalytics($request);
		}

		/**
		 * Set the GA4 property ID and verify it against the uploaded credentials.
		 * Mirrors the legacy set-property-id flow: a property ID that doesn't
		 * resolve is rejected so the SPA can keep the user on the form.
		 */
		public function updateAnalytics(Request $request) {
			$property_id = $request->bodyString("property_id");

			if ($property_id === "") {
				throw new BadRequestException("A property ID is required.", "missing_property_id");
			}

			$settings = BigTreeCMS::getSetting("bigtree-internal-google-analytics-4") ?: [];

			if (empty($settings["credentials"])) {
				throw new BadRequestException("Upload a service-account key before setting a property ID.", "missing_credentials");
			}

			$analytics = new \BigTreeGoogleAnalytics4();
			$analytics->setPropertyID($property_id);

			if (!$analytics->testCredentials()) {
				throw new BadRequestException("That property ID could not be verified with the uploaded credentials.", "verification_failed");
			}

			$analytics->setVerified();

			return $this->getAnalytics($request);
		}

		// — services —

		public function listServices(Request $request) {
			$out = [];

			foreach (OAuthBrokerService::SERVICES as $service => $def) {
				$settings = BigTreeCMS::getSetting($def["setting"]) ?: [];

				// "Connected" means the OAuth handshake finished (a token exists) —
				// not merely that a key/secret was entered.
				$out[$service] = [
					"connected" => !empty($settings["token"]) || !empty($settings["access_token"]),
					"identity" => $this->serviceIdentity($service, $settings),
					"key" => (string)($settings["key"] ?? ""),
					"has_secret" => !empty($settings["secret"]),
					"scope" => (string)($settings["scope"] ?? ""),
					"uses_scope" => !empty($def["scope"]),
					"test_environment" => !empty($settings["test_environment"]),
				];
			}

			return Response::ok($out);
		}

		public function disconnectService(Request $request) {
			$service = $request->routeParam("service");

			if (!isset(OAuthBrokerService::SERVICES[$service])) {
				throw new NotFoundException("Unknown service", "unknown_service");
			}

			BigTreeAdmin::updateInternalSettingValue(OAuthBrokerService::SERVICES[$service]["setting"], [], true);

			return Response::noContent();
		}

		// — media-presets —

		public function getMediaPresets(Request $request) {
			$settings = BigTreeJSONDB::get("config", "media-settings") ?: [];
			$presets = is_array($settings["presets"] ?? null) ? $settings["presets"] : [];

			return Response::ok([
				"presets" => array_values(array_filter($presets)),
			]);
		}

		public function updateMediaPresets(Request $request) {
			$incoming = is_array($request->body["presets"] ?? null) ? $request->body["presets"] : [];
			$existing = BigTreeJSONDB::get("config", "media-settings") ?: [];
			$presets = [];

			foreach ($incoming as $preset) {
				if (!is_array($preset)) {
					continue;
				}

				$id = (string)($preset["id"] ?? "");

				if ($id === "") {
					$id = uniqid();
				}

				$preset["id"] = $id;
				$preset["name"] = BigTree::safeEncode((string)($preset["name"] ?? ""));
				$presets[$id] = $preset;
			}

			$existing["presets"] = $presets;
			BigTreeJSONDB::update("config", "media-settings", $existing);

			return Response::ok(["presets" => array_values($presets)]);
		}

		// — file-metadata —

		public function getFileMetadata(Request $request) {
			$metadata = BigTreeJSONDB::get("config", "file-metadata") ?: [];

			return Response::ok([
				"file" => array_values(is_array($metadata["file"] ?? null) ? $metadata["file"] : []),
				"image" => array_values(is_array($metadata["image"] ?? null) ? $metadata["image"] : []),
				"video" => array_values(is_array($metadata["video"] ?? null) ? $metadata["video"] : []),
			]);
		}

		public function updateFileMetadata(Request $request) {
			$next = ["file" => [], "image" => [], "video" => []];

			foreach (["file", "image", "video"] as $bucket) {
				$rows = is_array($request->body[$bucket] ?? null) ? $request->body[$bucket] : [];

				foreach ($rows as $row) {
					if (!is_array($row) || empty($row["id"])) {
						continue;
					}

					$id = BigTree::safeEncode((string)$row["id"]);
					$next[$bucket][$id] = [
						"id" => $id,
						"title" => BigTree::safeEncode((string)($row["title"] ?? "")),
						"subtitle" => BigTree::safeEncode((string)($row["subtitle"] ?? "")),
						"type" => (string)($row["type"] ?? "text"),
						"settings" => is_array($row["settings"] ?? null) ? $row["settings"] : [],
					];
				}
			}

			BigTreeJSONDB::update("config", "file-metadata", $next);

			return Response::ok([
				"file" => array_values($next["file"]),
				"image" => array_values($next["image"]),
				"video" => array_values($next["video"]),
			]);
		}

		// — helpers —

		private function maskCloudSecrets($provider, array $settings) {
			$secret_keys = [
				"amazon" => ["secret"],
				"rackspace" => ["api_key", "token"],
				"google" => ["private_key", "client_secret"],
			];

			foreach ($secret_keys[$provider] ?? [] as $key) {
				if (!empty($settings[$key])) {
					$settings[$key] = "";
					$settings[$key . "_set"] = true;
				}
			}

			return $settings;
		}

		private function maskGatewaySecrets(array $settings) {
			foreach ($settings as $key => $value) {
				if ($this->isMaskedGatewayKey($key) && !empty($value)) {
					$settings[$key] = "";
					$settings[$key . "-set"] = true;
				}
			}

			return $settings;
		}

		private function isMaskedGatewayKey($key) {
			$haystacks = ["secret", "key", "password", "token", "signature"];

			foreach ($haystacks as $needle) {
				if (stripos($key, $needle) !== false) {
					return true;
				}
			}

			return false;
		}

		private function serviceIdentity($service, array $settings) {
			$candidates = [
				"twitter" => ["user_name", "screen_name", "username"],
				"instagram" => ["user_name", "username", "user", "user_id"],
				"youtube" => ["user_name", "channel_name", "channel_id"],
				"flickr" => ["user_name", "username", "user_id"],
				"salesforce" => ["user_name", "instance_url", "username"],
				"disqus" => ["user_name", "shortname", "username"],
				"facebook" => ["user_name", "page_name", "page_id"],
			];

			foreach ($candidates[$service] ?? [] as $key) {
				if (!empty($settings[$key])) {
					return (string)$settings[$key];
				}
			}

			return "";
		}
	}
