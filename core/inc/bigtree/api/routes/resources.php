<?php
	use BigTree\Services\ResourceService;
	use BigTree\Services\SystemConfigureService;

	return [
		// — Folders —
		"GET /resource-folders" => [
			"service" => [ResourceService::class, "listFolders"],
			"permission" => ["level" => 0],
			"query" => ["parent" => "int|min:0"],
		],
		"GET /resource-folders/flat" => [
			"service" => [ResourceService::class, "listFoldersFlat"],
			"permission" => ["level" => 0],
		],
		"POST /resource-folders" => [
			"service" => [ResourceService::class, "createFolder"],
			"permission" => ["level" => 0],
			"body" => ["parent" => "required|int|min:0", "name" => "required|string|max:255"],
			"audit" => ["table" => "bigtree_resource_folders", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /resource-folders/{id:int}" => [
			"service" => [ResourceService::class, "updateFolder"],
			"permission" => ["level" => 0],
			"body" => ["name" => "string|max:255", "parent" => "int|min:0"],
			"audit" => ["table" => "bigtree_resource_folders", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /resource-folders/{id:int}" => [
			"service" => [ResourceService::class, "deleteFolder"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_resource_folders", "type" => "deleted", "entry" => "%id%"],
		],

		// — Resources (search / upload first; static paths before {id}) —
		// Metadata definitions are developer-configured but must be readable by
		// anyone who can edit a file (the legacy file editor renders them for
		// every user) — hence level 0 here vs. level 2 on the configure routes.
		"GET /resources/metadata-fields" => [
			"service" => [SystemConfigureService::class, "getFileMetadata"],
			"permission" => ["level" => 0],
		],
		"GET /resources/search" => [
			"service" => [ResourceService::class, "search"],
			"permission" => ["level" => 0],
			"query" => ["q" => "required|string|max:200"],
		],
		"POST /resources/upload" => [
			"service" => [ResourceService::class, "upload"],
			"permission" => ["level" => 0],
			"multipart" => true,
			"audit" => ["table" => "bigtree_resources", "type" => "uploaded", "entry" => "%id%"],
		],
		"POST /resources/video" => [
			"service" => [ResourceService::class, "createManagedVideo"],
			"permission" => ["level" => 0],
			"body" => [
				"url" => "required|string|max:500",
				"folder" => "int|min:0",
			],
			"audit" => ["table" => "bigtree_resources", "type" => "created", "entry" => "%id%"],
		],

		// — Single resource —
		"GET /resources/{id:int}" => [
			"service" => [ResourceService::class, "getResource"],
			"permission" => ["level" => 0],
		],
		"PATCH /resources/{id:int}" => [
			"service" => [ResourceService::class, "updateResource"],
			"permission" => ["level" => 0],
			"body" => ["name" => "string|max:255", "folder" => "int|min:0", "metadata" => "array"],
			"audit" => ["table" => "bigtree_resources", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /resources/{id:int}" => [
			"service" => [ResourceService::class, "deleteResource"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_resources", "type" => "deleted", "entry" => "%id%"],
		],
		"POST /resources/{id:int}/replace" => [
			"service" => [ResourceService::class, "replaceResource"],
			"permission" => ["level" => 0],
			"multipart" => true,
			"audit" => ["table" => "bigtree_resources", "type" => "replaced", "entry" => "%id%"],
		],

		// — Crops —
		"POST /resources/{id:int}/crop" => [
			"service" => [ResourceService::class, "crop"],
			"permission" => ["level" => 0],
			"body" => [
				"x" => "required|int|min:0",
				"y" => "required|int|min:0",
				"width" => "required|int|min:1",
				"height" => "required|int|min:1",
				"target_width" => "int|min:1",
				"target_height" => "int|min:1",
				"prefix" => "string|max:64",
				"directory" => "string|max:255",
			],
			"audit" => ["table" => "bigtree_resources", "type" => "cropped", "entry" => "%id%"],
		],

		// — Allocations —
		"GET /resources/{id:int}/allocations" => [
			"service" => [ResourceService::class, "allocations"],
			"permission" => ["level" => 0],
		],
		// Enriched "used by" list (resolved location/title/status + SPA links).
		"GET /resources/{id:int}/usage" => [
			"service" => [ResourceService::class, "usage"],
			"permission" => ["level" => 0],
		],
		"POST /resources/{id:int}/allocations" => [
			"service" => [ResourceService::class, "allocate"],
			"permission" => ["level" => 0],
			"body" => ["table" => "required|string|max:255", "entry" => "required|string|max:255"],
			"audit" => ["table" => "bigtree_resource_allocation", "type" => "allocated", "entry" => "%id%"],
		],
		"DELETE /resources/{id:int}/allocations" => [
			"service" => [ResourceService::class, "deallocate"],
			"permission" => ["level" => 0],
			"body" => ["table" => "required|string|max:255", "entry" => "required|string|max:255"],
			"audit" => ["table" => "bigtree_resource_allocation", "type" => "deallocated", "entry" => "%id%"],
		],
	];
