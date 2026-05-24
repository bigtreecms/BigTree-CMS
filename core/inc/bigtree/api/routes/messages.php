<?php
	use BigTree\Services\MessageService;

	return [
		"GET /messages" => [
			"service" => [MessageService::class, "list"],
			"permission" => ["level" => 0],
			"query" => ["folder" => "string|in:in,sent", "page" => "int|min:1", "per_page" => "int|min:1|max:100"],
		],
		"GET /messages/unread-count" => [
			"service" => [MessageService::class, "unreadCount"],
			"permission" => ["level" => 0],
		],
		"POST /messages" => [
			"service" => [MessageService::class, "create"],
			"permission" => ["level" => 0],
			"body" => [
				"subject" => "required|string|max:255",
				"message" => "required|string",
				"recipients" => "required|array",
				"in_response_to" => "int|min:0",
			],
			"audit" => ["table" => "bigtree_messages", "type" => "sent", "entry" => "%id%"],
		],
		"GET /messages/{id:int}" => [
			"service" => [MessageService::class, "get"],
			"permission" => ["level" => 0],
		],
		"POST /messages/{id:int}/read" => [
			"service" => [MessageService::class, "markRead"],
			"permission" => ["level" => 0],
		],
	];
