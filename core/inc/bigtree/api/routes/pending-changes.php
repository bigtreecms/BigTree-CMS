<?php
	use BigTree\Services\PendingChangeService;

	return [
		"GET /pending-changes" => [
			"service" => [PendingChangeService::class, "list"],
			"permission" => ["level" => 0],
			"query" => ["page" => "int|min:1", "per_page" => "int|min:1|max:100", "mine" => "bool"],
		],
		"GET /pending-changes/{id:int}" => [
			"service" => [PendingChangeService::class, "get"],
			"permission" => ["level" => 0],
		],
		"POST /pending-changes/{id:int}/approve" => [
			"service" => [PendingChangeService::class, "approve"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pending_changes", "type" => "approved", "entry" => "%id%"],
		],
		"POST /pending-changes/{id:int}/reject" => [
			"service" => [PendingChangeService::class, "reject"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pending_changes", "type" => "rejected", "entry" => "%id%"],
		],
	];
