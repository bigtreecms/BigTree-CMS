<?php
	use BigTree\Services\PageService;

	return [
		"GET /pages" => [
			"service" => [PageService::class, "list"],
			"permission" => ["level" => 0],
			"query" => ["parent" => "int|min:0", "include_archived" => "bool"],
		],
		"GET /pages/search" => [
			"service" => [PageService::class, "search"],
			"permission" => ["level" => 0],
			"query" => ["q" => "required|string|max:200"],
		],

		"GET /pages/sites" => [
			"service" => [PageService::class, "sites"],
			"permission" => ["level" => 0],
		],
		"POST /pages" => [
			"service" => [PageService::class, "create"],
			"permission" => ["level" => 0],
			"body" => [
				"parent" => "int|min:0",
				"nav_title" => "required|string|max:1024",
				"title" => "string|max:1024",
				"route" => "string|max:1024",
				"in_nav" => "bool",
				"meta_keywords" => "string",
				"meta_description" => "string",
				"seo_invisible" => "bool",
				"template" => "string|max:255",
				"external" => "string|max:1024",
				"new_window" => "bool",
				"resources" => "array",
				"publish_at" => "string|max:32",
				"expire_at" => "string|max:32",
				"max_age" => "int|min:0",
				"tags" => "array",
				"open_graph" => "array",
				"trunk" => "bool",
				"publish" => "bool",
			],
			"audit" => ["table" => "bigtree_pages", "type" => "created", "entry" => "%id%"],
		],

		"GET /pages/pending/{pcid:int}" => [
			"service" => [PageService::class, "getPending"],
			"permission" => ["level" => 0],
			"query" => ["fields" => "string|max:255"],
		],
		"PATCH /pages/pending/{pcid:int}" => [
			"service" => [PageService::class, "updatePending"],
			"permission" => ["level" => 0],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_pending_changes", "type" => "updated", "entry" => "%pcid%"],
		],

		"GET /pages/{id:int}" => [
			"service" => [PageService::class, "get"],
			"permission" => ["level" => 0],
			"query" => ["fields" => "string|max:255", "pending" => "bool"],
		],
		"PATCH /pages/{id:int}" => [
			"service" => [PageService::class, "update"],
			"permission" => ["level" => 0],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_pages", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /pages/{id:int}" => [
			"service" => [PageService::class, "delete"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pages", "type" => "deleted", "entry" => "%id%"],
		],
		"POST /pages/{id:int}/archive" => [
			"service" => [PageService::class, "archive"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pages", "type" => "archived", "entry" => "%id%"],
		],
		"POST /pages/{id:int}/unarchive" => [
			"service" => [PageService::class, "unarchive"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pages", "type" => "unarchived", "entry" => "%id%"],
		],
		"POST /pages/{id:int}/duplicate" => [
			"service" => [PageService::class, "duplicate"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pages", "type" => "duplicated", "entry" => "%id%"],
		],
		"POST /pages/{id:int}/move" => [
			"service" => [PageService::class, "move"],
			"permission" => ["level" => 0],
			"body" => ["parent" => "required|int|min:0"],
			"audit" => ["table" => "bigtree_pages", "type" => "moved", "entry" => "%id%"],
		],
		"POST /pages/{parent:int}/reorder" => [
			"service" => [PageService::class, "reorder"],
			"permission" => ["level" => 0],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "bigtree_pages", "type" => "reordered", "entry" => "%parent%"],
		],

		"GET /pages/{id:int}/seo-rating" => [
			"service" => [PageService::class, "seoRating"],
			"permission" => ["level" => 0],
		],

		// Admin-only, matching legacy pages/access-levels.php's level gate.
		"GET /pages/{id:int}/access-levels" => [
			"service" => [PageService::class, "accessLevels"],
			"permission" => ["level" => 1],
		],

		"GET /pages/{id:int}/revisions" => [
			"service" => [PageService::class, "listRevisions"],
			"permission" => ["level" => 0],
		],
		"POST /pages/{id:int}/revisions" => [
			"service" => [PageService::class, "saveRevision"],
			"permission" => ["level" => 0],
			"body" => ["description" => "string|max:1024"],
			"audit" => ["table" => "bigtree_page_revisions", "type" => "saved", "entry" => "%id%"],
		],
		"DELETE /pages/{id:int}/revisions/{rev_id:int}" => [
			"service" => [PageService::class, "deleteRevision"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_page_revisions", "type" => "deleted", "entry" => "%rev_id%"],
		],
		"POST /pages/{id:int}/revisions/{rev_id:int}/restore" => [
			"service" => [PageService::class, "restoreRevision"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_pages", "type" => "revision_restored", "entry" => "%id%"],
		],
	];
