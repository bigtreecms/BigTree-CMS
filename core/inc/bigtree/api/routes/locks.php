<?php
	use BigTree\Services\LockService;

	return [
		"POST /locks" => [
			"service" => [LockService::class, "acquire"],
			"permission" => ["level" => 0],
			"body" => [
				// 191: `bigtree_locks`.`table` and `item_id` are both indexed, so
				// revision 512 narrowed them to varchar(191) on the way to utf8mb4.
				"table" => "required|string|max:191",
				"item_id" => "required|string|max:191",
				"title" => "string|max:1024",
				"force" => "bool",
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
