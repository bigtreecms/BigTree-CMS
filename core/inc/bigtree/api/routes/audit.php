<?php
	use BigTree\Services\AuditService;

	return [
		"GET /audit" => [
			"service" => [AuditService::class, "list"],
			"permission" => ["level" => 2],
			"query" => [
				"user" => "int",
				"table" => "string|max:255",
				"entry" => "string|max:255",
				"start" => "string|max:32",
				"end" => "string|max:32",
				"via" => "string|max:32",
				"include" => "string|max:32",
				"page" => "int",
				"per_page" => "int",
			],
		],

		"GET /audit/tables" => [
			"service" => [AuditService::class, "tables"],
			"permission" => ["level" => 2],
		],
	];
