<?php
	use BigTree\Services\AIChatService;

	return [
		// Level 0: any editor may chat. The registry filters which tools their
		// model is offered, and every executor re-checks server-side.
		"POST /ai/chat" => [
			"service" => [AIChatService::class, "chat"],
			"permission" => ["level" => 0],
			"body" => [
				"message" => "required|string|max:4000",
				"conversation_id" => "int|min:1",
			],
		],

		// Streaming counterpart (Phase 5): same turn over Server-Sent Events. The
		// service emits the stream and exits, bypassing the JSON envelope; setup
		// failures (gate/throttle/limits) still surface as normal JSON errors.
		"POST /ai/chat/stream" => [
			"service" => [AIChatService::class, "chatStream"],
			"permission" => ["level" => 0],
			"body" => [
				"message" => "required|string|max:4000",
				"conversation_id" => "int|min:1",
			],
		],

		"GET /ai/conversations" => [
			"service" => [AIChatService::class, "listConversations"],
			"permission" => ["level" => 0],
			"query" => [
				"page" => "int|min:1",
				"per_page" => "int|min:1|max:100",
			],
		],

		"GET /ai/conversations/{id:int}" => [
			"service" => [AIChatService::class, "getConversation"],
			"permission" => ["level" => 0],
		],

		"DELETE /ai/conversations/{id:int}" => [
			"service" => [AIChatService::class, "deleteConversation"],
			"permission" => ["level" => 0],
		],

		// Proposal lifecycle (Phase 3): approve runs the staged mutation server-side,
		// re-checking permission at approval time; reject discards it. The id is a
		// proposal id (string segment), owner-scoped so a non-owner 404s.
		"POST /ai/proposals/{id}/approve" => [
			"service" => [AIChatService::class, "approveProposal"],
			"permission" => ["level" => 0],
		],

		"POST /ai/proposals/{id}/reject" => [
			"service" => [AIChatService::class, "rejectProposal"],
			"permission" => ["level" => 0],
		],
	];
