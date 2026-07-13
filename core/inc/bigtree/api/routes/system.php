<?php
	use BigTree\Services\OAuthBrokerService;
	use BigTree\Services\SystemConfigureService;
	use BigTree\Services\SystemService;

	return [
		"GET /system/version" => [
			"service" => [SystemService::class, "version"],
			"permission" => ["level" => 0],
		],

		"GET /system/site" => [
			"service" => [SystemService::class, "site"],
			"permission" => ["level" => 0],
		],

		"GET /system/status" => [
			"service" => [SystemService::class, "siteStatus"],
			"permission" => ["level" => 2],
		],

		"POST /system/cache/clear" => [
			"service" => [SystemService::class, "clearCache"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "system", "type" => "cache_cleared", "entry" => "0"],
		],

		"GET /system/security-policy" => [
			"service" => [SystemService::class, "getSecurityPolicy"],
			"permission" => ["level" => 2],
		],

		"PATCH /system/security-policy" => [
			"service" => [SystemService::class, "updateSecurityPolicy"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "security_policy_updated", "entry" => "0"],
		],

		"POST /system/bans/unban-ip" => [
			"service" => [SystemService::class, "unbanIP"],
			"permission" => ["level" => 2],
			"body" => ["ip" => "required|string|max:45"],
			"audit" => ["table" => "bigtree_login_bans", "type" => "ip_unbanned", "entry" => "%ip%"],
		],

		"POST /system/bans/unban-user" => [
			"service" => [SystemService::class, "unbanUser"],
			"permission" => ["level" => 2],
			"body" => ["user_id" => "required|int"],
			"audit" => ["table" => "bigtree_login_bans", "type" => "user_unbanned", "entry" => "%user_id%"],
		],

		// — Database backups —
		// Backup file generation is synchronous and slow on large DBs. Rate-limited
		// to 2/min so a runaway client can't pin the server.
		"POST /system/backup" => [
			"service" => [SystemService::class, "createBackup"],
			"permission" => ["level" => 2],
			"rate_limit" => ["per_minute" => 2],
			"audit" => ["table" => "system", "type" => "backup_created", "entry" => "%backup_id%"],
		],
		"GET /system/backup" => [
			"service" => [SystemService::class, "listBackups"],
			"permission" => ["level" => 2],
		],
		"DELETE /system/backup/{id}" => [
			"service" => [SystemService::class, "deleteBackup"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "system", "type" => "backup_deleted", "entry" => "%id%"],
		],
		// Public + token-gated so the SPA can use a browser-native <a download> link.
		// The token is HMAC-signed against the JWT secret with a short TTL and is
		// scoped to a specific backup_id + issuing user.
		"GET /system/backup/{id}/download" => [
			"service" => [SystemService::class, "downloadBackup"],
			"permission" => "public",
			"query" => ["token" => "required|string|max:1024"],
			"rate_limit" => ["per_minute" => 10],
		],

		// — Configure section —
		// Every area is backed by a `bigtree-internal-*` setting (or a JSONDB
		// "config" row), all gated to developer level. Defined in
		// SystemConfigureService — see the docblock there for the full mapping.

		"GET /system/upgrade/check" => [
			"service" => [SystemService::class, "checkUpgrade"],
			"permission" => ["level" => 2],
		],
		"POST /system/upgrade/download" => [
			"service" => [SystemService::class, "downloadUpgrade"],
			"permission" => ["level" => 2],
			"body" => ["type" => "required|string|in:revision,minor"],
			"rate_limit" => ["per_minute" => 4],
			"audit" => ["table" => "system", "type" => "upgrade_downloaded", "entry" => "%type%"],
		],
		"POST /system/upgrade/install" => [
			"service" => [SystemService::class, "installUpgrade"],
			"permission" => ["level" => 2],
			"body" => [
				"ftp_username" => "string|max:255",
				"ftp_password" => "string|max:255",
				"ftp_root" => "string|max:1024",
			],
			"allow_unknown" => false,
			"audit" => ["table" => "system", "type" => "upgrade_installed", "entry" => "0"],
		],
		"GET /system/upgrade/migrations" => [
			"service" => [SystemService::class, "upgradeMigrations"],
			"permission" => ["level" => 2],
		],
		"POST /system/upgrade/migrate" => [
			"service" => [SystemService::class, "runUpgradeMigration"],
			"permission" => ["level" => 2],
			"body" => [
				"script" => "required|string|max:255",
				"page" => "int|min:1",
				"total_pages" => "int|min:1",
			],
		],

		"GET /system/configure/email" => [
			"service" => [SystemConfigureService::class, "getEmail"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/email" => [
			"service" => [SystemConfigureService::class, "updateEmail"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "email_configured", "entry" => "bigtree-internal-email-service"],
		],

		"GET /system/configure/geocoding" => [
			"service" => [SystemConfigureService::class, "getGeocoding"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/geocoding" => [
			"service" => [SystemConfigureService::class, "updateGeocoding"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "geocoding_configured", "entry" => "bigtree-internal-geocoding-service"],
		],

		"GET /system/configure/cloud-storage" => [
			"service" => [SystemConfigureService::class, "getCloudStorage"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/cloud-storage/default" => [
			"service" => [SystemConfigureService::class, "updateCloudStorageDefault"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "cloud_storage_default", "entry" => "bigtree-internal-storage"],
		],
		"PUT /system/configure/cloud-storage/{provider}" => [
			"service" => [SystemConfigureService::class, "updateCloudStorageProvider"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "cloud_storage_configured", "entry" => "%provider%"],
		],
		"POST /system/configure/cloud-storage/google/private-key" => [
			"service" => [SystemConfigureService::class, "uploadGoogleStorageKey"],
			"permission" => ["level" => 2],
			"multipart" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "cloud_storage_configured", "entry" => "bigtree-internal-cloud-storage"],
		],
		"POST /system/configure/cloud-storage/amazon/recache" => [
			"service" => [SystemConfigureService::class, "recacheAmazonStorage"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
		],

		"GET /system/configure/payment-gateway" => [
			"service" => [SystemConfigureService::class, "getPaymentGateway"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/payment-gateway" => [
			"service" => [SystemConfigureService::class, "updatePaymentGateway"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "payment_gateway_configured", "entry" => "bigtree-internal-payment-gateway"],
		],
		"POST /system/configure/payment-gateway/linkpoint/certificate" => [
			"service" => [SystemConfigureService::class, "uploadLinkpointCertificate"],
			"permission" => ["level" => 2],
			"multipart" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "payment_gateway_configured", "entry" => "bigtree-internal-payment-gateway"],
		],

		"GET /system/configure/analytics" => [
			"service" => [SystemConfigureService::class, "getAnalytics"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/analytics" => [
			"service" => [SystemConfigureService::class, "updateAnalytics"],
			"permission" => ["level" => 2],
			"body" => ["property_id" => "required|string|max:64"],
			"audit" => ["table" => "bigtree_settings", "type" => "analytics_configured", "entry" => "bigtree-internal-google-analytics-4"],
		],
		"POST /system/configure/analytics/credentials" => [
			"service" => [SystemConfigureService::class, "uploadAnalyticsCredentials"],
			"permission" => ["level" => 2],
			"multipart" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "analytics_configured", "entry" => "bigtree-internal-google-analytics-4"],
		],
		"DELETE /system/configure/analytics" => [
			"service" => [SystemConfigureService::class, "disconnectAnalytics"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "bigtree_settings", "type" => "analytics_disconnected", "entry" => "bigtree-internal-google-analytics-4"],
		],

		"GET /system/configure/services" => [
			"service" => [SystemConfigureService::class, "listServices"],
			"permission" => ["level" => 2],
		],
		"DELETE /system/configure/services/{service}" => [
			"service" => [SystemConfigureService::class, "disconnectService"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "bigtree_settings", "type" => "service_disconnected", "entry" => "%service%"],
		],

		// OAuth broker. `start` is Bearer-authed and returns a launch URL; `launch`
		// and `callback` are public browser-redirect endpoints authenticated by the
		// signed token they carry (see OAuthBrokerService).
		"POST /system/configure/services/{service}/oauth/start" => [
			"service" => [OAuthBrokerService::class, "startService"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
		],
		"POST /system/configure/cloud-storage/google/oauth/start" => [
			"service" => [OAuthBrokerService::class, "startGoogleStorage"],
			"permission" => ["level" => 2],
		],
		"GET /system/configure/oauth/launch" => [
			"service" => [OAuthBrokerService::class, "launch"],
			"permission" => "public",
			"query" => ["token" => "required|string|max:2048"],
			"rate_limit" => ["per_minute" => 30],
		],
		"GET /system/configure/oauth/callback" => [
			"service" => [OAuthBrokerService::class, "callback"],
			"permission" => "public",
			"rate_limit" => ["per_minute" => 30],
		],

		"GET /system/configure/media-presets" => [
			"service" => [SystemConfigureService::class, "getMediaPresets"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/media-presets" => [
			"service" => [SystemConfigureService::class, "updateMediaPresets"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "media_presets_updated", "entry" => "media-settings"],
		],

		"GET /system/configure/file-metadata" => [
			"service" => [SystemConfigureService::class, "getFileMetadata"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/file-metadata" => [
			"service" => [SystemConfigureService::class, "updateFileMetadata"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "file_metadata_updated", "entry" => "file-metadata"],
		],

		"GET /system/configure/ai" => [
			"service" => [SystemConfigureService::class, "getAI"],
			"permission" => ["level" => 2],
		],
		"PUT /system/configure/ai" => [
			"service" => [SystemConfigureService::class, "updateAI"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "ai_configured", "entry" => "bigtree-internal-ai-service"],
		],
		"POST /system/configure/ai/embeddings/reindex" => [
			"service" => [SystemConfigureService::class, "reindexAIEmbeddings"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "ai_embeddings_reindex", "entry" => "bigtree-internal-ai-service"],
		],
	];
