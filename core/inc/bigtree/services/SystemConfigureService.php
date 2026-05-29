<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
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
	 * OAuth handshakes (analytics, services) still happen on the legacy admin —
	 * exposing them through the API requires upstream SDK work that's out of
	 * scope for this pass. The GET endpoints return enough state for the SPA to
	 * show connection status and let the user disconnect; reconnecting happens
	 * via a deep-link to /admin/developer/{area}.
	 */
	class SystemConfigureService {

		/** Providers the SPA shows in the Configure index card grid. */
		private const SERVICES_AVAILABLE = [
			"twitter", "instagram", "youtube", "flickr", "salesforce", "disqus", "facebook",
		];

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
				throw new BadRequestException("Unknown geocoding service", "invalid_service", 400);
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
			$provider = (string)$request->route_params["provider"];

			if (!in_array($provider, ["amazon", "rackspace", "google"], true)) {
				throw new NotFoundException("Unknown cloud provider", "unknown_provider", 404);
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

		public function updateCloudStorageDefault(Request $request) {
			$service = (string)($request->body["service"] ?? "local");
			$container = (string)($request->body["container"] ?? "");

			if (!in_array($service, ["local", "amazon", "rackspace", "google"], true)) {
				throw new BadRequestException("Unknown storage service", "invalid_service", 400);
			}

			$storage = BigTreeCMS::getSetting("bigtree-internal-storage") ?: [];
			$storage["Service"] = $service;

			if ($container !== "") {
				$storage["Container"] = $container;
			}

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-storage", $storage, true);

			return Response::ok([
				"default_service" => $service,
				"default_container" => $storage["Container"] ?? "",
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
			$service = (string)($request->body["service"] ?? "");
			$incoming = is_array($request->body["settings"] ?? null) ? $request->body["settings"] : [];

			if (!in_array($service, ["", "authorize.net", "paypal", "paypal-rest", "payflow", "linkpoint"], true)) {
				throw new BadRequestException("Unknown payment gateway", "invalid_service", 400);
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

		// — analytics —

		public function getAnalytics(Request $request) {
			$settings = BigTreeCMS::getSetting("bigtree-internal-google-analytics-4") ?: [];
			$credentials = is_array($settings["credentials"] ?? null) ? $settings["credentials"] : [];

			return Response::ok([
				"verified" => !empty($settings["verified"]),
				"property_id" => (string)($settings["property_id"] ?? ""),
				"service_account" => (string)($credentials["client_email"] ?? ""),
				"setup_url" => rtrim(ADMIN_ROOT, "/") . "/developer/analytics/",
			]);
		}

		public function disconnectAnalytics(Request $request) {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-google-analytics-4", [], true);

			return Response::noContent();
		}

		// — services —

		public function listServices(Request $request) {
			$out = [];

			foreach (self::SERVICES_AVAILABLE as $service) {
				$settings = BigTreeCMS::getSetting("bigtree-internal-$service") ?: [];
				$out[$service] = [
					"connected" => !empty($settings["connected"]) || !empty($settings["token"]) || !empty($settings["access_token"]) || !empty($settings["key"]),
					"identity" => $this->serviceIdentity($service, $settings),
				];
			}

			$out["_setup_url_base"] = rtrim(ADMIN_ROOT, "/") . "/developer/services/";

			return Response::ok($out);
		}

		public function disconnectService(Request $request) {
			$service = (string)$request->route_params["service"];

			if (!in_array($service, self::SERVICES_AVAILABLE, true)) {
				throw new NotFoundException("Unknown service", "unknown_service", 404);
			}

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-$service", [], true);

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
				"twitter" => ["screen_name", "username"],
				"instagram" => ["username", "user", "user_id"],
				"youtube" => ["channel_name", "channel_id"],
				"flickr" => ["username", "user_id"],
				"salesforce" => ["instance_url", "username"],
				"disqus" => ["shortname", "username"],
				"facebook" => ["page_name", "page_id"],
			];

			foreach ($candidates[$service] ?? [] as $key) {
				if (!empty($settings[$key])) {
					return (string)$settings[$key];
				}
			}

			return "";
		}
	}
