<?php
	use BigTree\Services\AutoModuleService;

	return [
		"GET /modules/{id:int}/entries" => [
			"service" => [AutoModuleService::class, "list"],
			"permission" => ["module" => "%id%", "min" => "v"],
			"query" => ["page" => "int|min:1", "q" => "string|max:200", "sort" => "string|max:64", "view" => "int|min:0"],
		],
		"POST /modules/{id:int}/entries" => [
			"service" => [AutoModuleService::class, "create"],
			"permission" => ["module" => "%id%", "min" => "e"],
			"allow_unknown" => true,
			"audit" => ["table" => "module_entry", "type" => "created", "entry" => "%id%"],
		],
		"GET /modules/{id:int}/entries/{eid:int}" => [
			"service" => [AutoModuleService::class, "get"],
			"permission" => ["module" => "%id%", "min" => "v"],
		],
		"PATCH /modules/{id:int}/entries/{eid:int}" => [
			"service" => [AutoModuleService::class, "update"],
			"permission" => ["module" => "%id%", "min" => "e"],
			"allow_unknown" => true,
			"audit" => ["table" => "module_entry", "type" => "updated", "entry" => "%eid%"],
		],
		"DELETE /modules/{id:int}/entries/{eid:int}" => [
			"service" => [AutoModuleService::class, "delete"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"audit" => ["table" => "module_entry", "type" => "deleted", "entry" => "%eid%"],
		],
		"POST /modules/{id:int}/entries/reorder" => [
			"service" => [AutoModuleService::class, "reorder"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "module_entry", "type" => "reordered", "entry" => "%id%"],
		],
	];
