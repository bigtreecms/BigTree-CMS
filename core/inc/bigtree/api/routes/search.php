<?php
	use BigTree\Services\SearchService;

	return [
		"GET /search" => [
			"service" => [SearchService::class, "search"],
			"permission" => ["level" => 0],
			"query" => [
				"q" => "required|string|max:200",
				"limit" => "int|min:1|max:50",
				"types" => "string|max:200",
			],
		],

		"POST /search/ai" => [
			"service" => [SearchService::class, "aiSearch"],
			"permission" => ["level" => 0],
			"allow_unknown" => true,
			"body" => [
				"q" => "required|string|max:500",
				"limit" => "int|min:1|max:20",
			],
		],
	];
