<?php
	use BigTree\Services\SystemService;

	return [
		"GET /openapi.json" => [
			"service" => [SystemService::class, "openapi"],
			"permission" => "public",
			"rate_limit" => ["per_minute" => 30],
		],
	];
