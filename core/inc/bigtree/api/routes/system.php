<?php
	use BigTree\Services\SystemService;

	return [
		"GET /system/version" => [
			"service" => [SystemService::class, "version"],
			"permission" => ["level" => 0],
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
	];
