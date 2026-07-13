<?php
	namespace BigTree\Services;

	use BigTree\Api\Flag;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Jwt;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeCMS;
	use BigTree;

	/**
	 * SPA-aware OAuth broker for the Developer → Configure "Services" and Google
	 * Cloud Storage integrations.
	 *
	 * The legacy admin ran these handshakes on session-authenticated pages whose
	 * callback URLs (/admin/developer/services/{svc}/return/) are registered with
	 * each provider. The SPA is stateless (Bearer JWT), so this broker carries
	 * identity through the browser-redirect flow with a short-lived signed token:
	 *
	 *   1. POST .../oauth/start (Bearer, level 2) — saves the entered credentials
	 *      and returns a `launch_url` containing a signed token (user + service).
	 *   2. The SPA does a full-page navigation to GET .../oauth/launch?token=… ,
	 *      which validates the token and 302s to the provider's authorize URL.
	 *      OAuth2 passes the token as `state`; OAuth1 (Twitter/Flickr) round-trips
	 *      it on the callback query string.
	 *   3. The provider redirects to GET .../oauth/callback (public), which
	 *      validates the token, completes the token exchange via the existing
	 *      BigTree API classes, and 302s back into the SPA.
	 *
	 * IMPORTANT: the callback now lives at /admin/api/v1/system/configure/oauth/callback.
	 * That redirect URI must be registered with each provider (Twitter, Facebook,
	 * Google, etc.) in place of the old legacy URL.
	 */
	class OAuthBrokerService {

		const TOKEN_ISS = "bigtree-oauth";
		const TOKEN_AUD = "oauth";
		const TOKEN_TTL = 600;

		/**
		 * Provider registry. `oauth1` services (Twitter, Flickr) fetch a request
		 * token before redirecting and round-trip our token via the callback query;
		 * everything else uses the standard OAuth2 code flow with a `state` param.
		 */
		public const SERVICES = [
			"twitter" => ["class" => "BigTreeTwitterAPI", "setting" => "bigtree-internal-twitter-api", "oauth1" => true, "scope" => false],
			"flickr" => ["class" => "BigTreeFlickrAPI", "setting" => "bigtree-internal-flickr-api", "oauth1" => true, "scope" => false],
			"facebook" => ["class" => "BigTreeFacebookAPI", "setting" => "bigtree-internal-facebook-api", "oauth1" => false, "scope" => true],
			"instagram" => ["class" => "BigTreeInstagramAPI", "setting" => "bigtree-internal-instagram-api", "oauth1" => false, "scope" => false],
			"youtube" => ["class" => "BigTreeYouTubeAPI", "setting" => "bigtree-internal-youtube-api", "oauth1" => false, "scope" => false],
			"disqus" => ["class" => "BigTreeDisqusAPI", "setting" => "bigtree-internal-disqus-api", "oauth1" => false, "scope" => true],
			"salesforce" => ["class" => "BigTreeSalesforceAPI", "setting" => "bigtree-internal-salesforce-api", "oauth1" => false, "scope" => false],
		];

		// — start endpoints (Bearer, level 2) —

		/**
		 * Persist the entered credentials for a service and hand the SPA a
		 * launch URL that begins the handshake.
		 */
		public function startService(Request $request) {
			$service = $request->routeParam("service");

			if (!isset(self::SERVICES[$service])) {
				throw new NotFoundException("Unknown service", "unknown_service");
			}

			$def = self::SERVICES[$service];
			$key = $request->bodyString("key");
			$secret = $request->bodyString("secret");

			if ($key === "" || $secret === "") {
				throw new BadRequestException("A key and secret are required to connect.", "missing_credentials");
			}

			$settings = BigTreeCMS::getSetting($def["setting"]) ?: [];
			$settings["key"] = $key;
			$settings["secret"] = $secret;

			if (!empty($def["scope"])) {
				$settings["scope"] = $request->bodyString("scope", $settings["scope"] ?? "", false);
			}

			$settings["test_environment"] = Flag::checkbox($request->body["test_environment"] ?? null);

			SettingService::updateInternalValue($def["setting"], $settings, true);

			return Response::ok(["launch_url" => $this->launchUrl($this->mintToken((int)$request->user->id, $service, "service"))]);
		}

		/**
		 * Promote the SPA's nested Google credentials to the top level the OAuth
		 * base class reads, then hand back a launch URL.
		 */
		public function startGoogleStorage(Request $request) {
			$cloud = BigTreeCMS::getSetting("bigtree-internal-cloud-storage") ?: [];
			$google = is_array($cloud["google"] ?? null) ? $cloud["google"] : [];

			$key = trim((string)($google["key"] ?? ($cloud["key"] ?? "")));
			$secret = trim((string)($google["secret"] ?? ($cloud["secret"] ?? "")));

			if ($key === "" || $secret === "") {
				throw new BadRequestException("Enter the Google client ID and secret before connecting.", "missing_credentials");
			}

			// The OAuth base reads top-level key/secret/project.
			$cloud["key"] = $key;
			$cloud["secret"] = $secret;
			$cloud["project"] = (string)($google["project"] ?? ($cloud["project"] ?? ""));

			SettingService::updateInternalValue("bigtree-internal-cloud-storage", $cloud, true);

			return Response::ok(["launch_url" => $this->launchUrl($this->mintToken((int)$request->user->id, "google", "gcs"))]);
		}

		// — launch / callback (public, token-authenticated) —

		/** Validate the launch token and redirect the browser to the provider. */
		public function launch(Request $request) {
			$claims = $this->readToken($request->queryString("token", "", false));
			$callback = $this->callbackUrl();

			if ($claims["kind"] === "gcs") {
				$api = new \BigTreeCloudStorage("google");
				$api->ReturnURL = $callback;
				$this->redirectOAuth2($api, $claims["raw"]);
			}

			$def = self::SERVICES[$claims["svc"]] ?? null;

			if (!$def) {
				throw new NotFoundException("Unknown service", "unknown_service");
			}

			$class = $def["class"];
			$api = new $class();

			if (!empty($def["oauth1"])) {
				// OAuth1: our token must survive the round-trip, so carry it on the
				// callback URL the provider redirects back to.
				$api->ReturnURL = $callback . "?state=" . urlencode($claims["raw"]);
				$api->oAuthRedirect();
				die();
			}

			$api->ReturnURL = $callback;
			$this->redirectOAuth2($api, $claims["raw"]);
		}

		/** Provider redirect target: validate state, exchange the token, return to the SPA. */
		public function callback(Request $request) {
			$state = $request->queryString("state", "", false);

			try {
				$claims = $this->readToken($state);
			} catch (\Throwable $e) {
				$this->redirectToSpa("services", ["error" => "invalid_state"]);
			}

			$callback = $this->callbackUrl();
			$code = (string)($request->query["code"] ?? ($request->query["oauth_verifier"] ?? ""));

			if ($claims["kind"] === "gcs") {
				$api = new \BigTreeCloudStorage("google");
				$api->ReturnURL = $callback;
				$api->oAuthSetToken($code);

				if ($api->OAuthError) {
					$this->redirectToSpa("cloud-storage", ["error" => "oauth_failed"]);
				}

				$cloud = BigTreeCMS::getSetting("bigtree-internal-cloud-storage") ?: [];
				$cloud["google"] = is_array($cloud["google"] ?? null) ? $cloud["google"] : [];
				$cloud["google"]["active"] = true;
				SettingService::updateInternalValue("bigtree-internal-cloud-storage", $cloud, true);

				$this->redirectToSpa("cloud-storage", ["connected" => "google"]);
			}

			$def = self::SERVICES[$claims["svc"]] ?? null;

			if (!$def) {
				$this->redirectToSpa("services", ["error" => "unknown_service"]);
			}

			$class = $def["class"];
			$api = new $class();
			$api->ReturnURL = $callback;
			$api->oAuthSetToken($code);

			if ($api->OAuthError || !$api->Connected) {
				$this->redirectToSpa("services", ["error" => "oauth_failed"]);
			}

			$this->redirectToSpa("services", ["connected" => $claims["svc"]]);
		}

		// — helpers —

		/** Build the standard OAuth2 authorize redirect (mirrors the base class) + state, then exit. */
		private function redirectOAuth2($api, $state) {
			$url = $api->AuthorizeURL
				. "?client_id=" . urlencode((string)($api->Settings["key"] ?? ""))
				. "&redirect_uri=" . urlencode($api->ReturnURL)
				. "&response_type=code"
				. "&scope=" . urlencode((string)($api->Scope ?: ""))
				. "&approval_prompt=force"
				. "&access_type=offline"
				. "&state=" . urlencode($state);

			header("Location: " . $url);
			die();
		}

		private function callbackUrl() {

			return rtrim(ADMIN_ROOT, "/") . "/api/v1/system/configure/oauth/callback";
		}

		private function launchUrl($token) {

			return rtrim(ADMIN_ROOT, "/") . "/api/v1/system/configure/oauth/launch?token=" . urlencode($token);
		}

		private function redirectToSpa($screen, array $params) {
			$query = http_build_query($params);
			$base = rtrim(ADMIN_ROOT, "/") . "/spa/developer/configure/" . $screen;

			header("Location: " . $base . ($query ? "?" . $query : ""));
			die();
		}

		private function mintToken($user_id, $service, $kind) {
			$now = time();

			return Jwt::encode([
				"iss" => self::TOKEN_ISS,
				"aud" => self::TOKEN_AUD,
				"sub" => (string)$user_id,
				"svc" => $service,
				"kind" => $kind,
				"iat" => $now,
				"exp" => $now + self::TOKEN_TTL,
			], Jwt::currentSecret());
		}

		/** Verify a launch/state token; returns its claims plus the raw token. */
		private function readToken($token) {
			if ($token === "") {
				throw new BadRequestException("Missing OAuth token", "missing_token");
			}

			$claims = Jwt::decode($token, Jwt::secrets(), self::TOKEN_ISS, self::TOKEN_AUD);
			$claims["raw"] = $token;

			return $claims;
		}
	}
