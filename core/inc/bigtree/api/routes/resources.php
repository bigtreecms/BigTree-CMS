<?php
	use BigTree\Services\ResourceService;

	return [
		// — Folders —
		"GET /resource-folders" => [
			"service" => [ResourceService::class, "listFolders"],
			"permission" => ["level" => 0],
			"query" => ["parent" => "int|min:0"],
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
