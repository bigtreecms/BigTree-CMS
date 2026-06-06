<?php
	use BigTree\Services\AutoModuleService;

	return [
		"GET /modules/{id}/entries" => [
			"service" => [AutoModuleService::class, "list"],
			"permission" => ["module" => "%id%", "min" => "v"],
			"query" => ["page" => "int|min:1", "q" => "string|max:200", "sort" => "string|max:64", "view" => "string|max:128"],
		],
		"POST /modules/{id}/entries" => [
			"service" => [AutoModuleService::class, "create"],
			"permission" => ["module" => "%id%", "min" => "e"],
			"allow_unknown" => true,
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "created", "entry" => "%id%"],
		],
		// `form` joins `view` here because edit screens reached via a form action
		// (no view) send the form id as the authoritative table reference.
		"GET /modules/{id}/entries/{eid:int}" => [
			"service" => [AutoModuleService::class, "get"],
			"permission" => ["module" => "%id%", "min" => "v"],
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
		],
		"PATCH /modules/{id}/entries/{eid:int}" => [
			"service" => [AutoModuleService::class, "update"],
			"permission" => ["module" => "%id%", "min" => "e"],
			"allow_unknown" => true,
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "updated", "entry" => "%eid%"],
		],
		"DELETE /modules/{id}/entries/{eid:int}" => [
			"service" => [AutoModuleService::class, "delete"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "deleted", "entry" => "%eid%"],
		],
		"POST /modules/{id}/entries/reorder" => [
			"service" => [AutoModuleService::class, "reorder"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"body" => ["ids" => "required|array", "view" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "reordered", "entry" => "%id%"],
		],
		"POST /modules/{id}/entries/{eid:int}/archive" => [
			"service" => [AutoModuleService::class, "toggleArchive"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "archived", "entry" => "%eid%"],
		],
		"POST /modules/{id}/entries/{eid:int}/approve" => [
			"service" => [AutoModuleService::class, "toggleApprove"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "approved", "entry" => "%eid%"],
		],
		"POST /modules/{id}/entries/{eid:int}/feature" => [
			"service" => [AutoModuleService::class, "toggleFeature"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128"],
			"audit" => ["table" => "module_entry", "type" => "featured", "entry" => "%eid%"],
		],
	];
