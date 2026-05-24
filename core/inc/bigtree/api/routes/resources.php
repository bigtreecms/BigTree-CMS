<?php
	use BigTree\Services\ResourceService;

	return [
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

		"GET /resources/search" => [
			"service" => [ResourceService::class, "search"],
			"permission" => ["level" => 0],
			"query" => ["q" => "required|string|max:200"],
		],
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
		"GET /resources/{id:int}/allocations" => [
			"service" => [ResourceService::class, "allocations"],
			"permission" => ["level" => 0],
		],
	];
