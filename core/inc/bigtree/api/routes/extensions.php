<?php
	use BigTree\Services\ExtensionService;

	return [
		"GET /extensions" => [
			"service" => [ExtensionService::class, "list"],
			"permission" => ["level" => 2],
			"query" => ["sort" => "string|max:64"],
		],
		"GET /extensions/updates" => [
			"service" => [ExtensionService::class, "updates"],
			"permission" => ["level" => 2],
		],
		"POST /extensions/recache-hooks" => [
			"service" => [ExtensionService::class, "recacheHooks"],
			"permission" => ["level" => 2],
		],
		"POST /extensions/install/unpack" => [
			"service" => [ExtensionService::class, "installUnpack"],
			"permission" => ["level" => 2],
			"multipart" => true,
		],
		"POST /extensions/install/process" => [
			"service" => [ExtensionService::class, "installProcess"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "extensions", "type" => "installed", "entry" => "%id%"],
		],
		"GET /extensions/build/licenses" => [
			"service" => [ExtensionService::class, "buildLicenses"],
			"permission" => ["level" => 2],
		],
		"POST /extensions/build/inspect" => [
			"service" => [ExtensionService::class, "buildInspect"],
			"permission" => ["level" => 2],
		],
		"POST /extensions/build" => [
			"service" => [ExtensionService::class, "buildPackage"],
			"permission" => ["level" => 2],
			"body" => [
				"id" => "required|string|max:128",
				"title" => "required|string|max:255",
			],
			"allow_unknown" => true,
			"audit" => ["table" => "extensions", "type" => "built", "entry" => "%id%"],
		],
		// Public + token-gated so the SPA can use a browser-native download link.
		"GET /extensions/build/download" => [
			"service" => [ExtensionService::class, "downloadPackage"],
			"permission" => "public",
			"query" => ["id" => "required|string|max:128", "token" => "required|string|max:2048"],
			"rate_limit" => ["per_minute" => 10],
		],
		"GET /extensions/{id}" => [
			"service" => [ExtensionService::class, "get"],
			"permission" => ["level" => 2],
		],
		"POST /extensions/{id}/upgrade" => [
			"service" => [ExtensionService::class, "upgrade"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "extensions", "type" => "upgraded", "entry" => "%id%"],
		],
		"DELETE /extensions/{id}" => [
			"service" => [ExtensionService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "extensions", "type" => "deleted", "entry" => "%id%"],
		],
	];
