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
	];
