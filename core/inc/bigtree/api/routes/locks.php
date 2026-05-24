<?php
	use BigTree\Services\LockService;

	return [
		"POST /locks" => [
			"service" => [LockService::class, "acquire"],
			"permission" => ["level" => 0],
			"body" => [
				"table" => "required|string|max:255",
				"item_id" => "required|string|max:255",
				"title" => "string|max:1024",
			],
		],
		"POST /locks/{id:int}/refresh" => [
			"service" => [LockService::class, "refresh"],
			"permission" => ["level" => 0],
		],
		"DELETE /locks/{id:int}" => [
			"service" => [LockService::class, "release"],
			"permission" => ["level" => 0],
		],
	];
