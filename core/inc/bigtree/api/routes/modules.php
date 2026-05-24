<?php
	use BigTree\Services\ModuleService;

	return [
		"GET /modules" => ["service" => [ModuleService::class, "list"], "permission" => ["level" => 0]],
		"GET /modules/{id:int}" => [
			"service" => [ModuleService::class, "get"],
			"permission" => ["level" => 0],
		],
		"POST /modules" => [
			"service" => [ModuleService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"name" => "required|string|max:255",
				"group" => "int|min:0",
				"class" => "string|max:255",
				"table" => "string|max:255",
				"gbp" => "array",
				"icon" => "string|max:64",
				"route" => "string|max:127",
				"graphql" => "bool",
				"graphql_type" => "string|max:128",
			],
			"audit" => ["table" => "modules", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}" => [
			"service" => [ModuleService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "modules", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /modules/{id:int}" => [
			"service" => [ModuleService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "modules", "type" => "deleted", "entry" => "%id%"],
		],
		"POST /modules/reorder" => [
			"service" => [ModuleService::class, "reorder"],
			"permission" => ["level" => 2],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "modules", "type" => "reordered", "entry" => "0"],
		],

		// — Sub-resources (reads visible to module:v, writes level:2) —

		"GET /modules/{id:int}/actions" => ["service" => [ModuleService::class, "actions"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id:int}/actions" => [
			"service" => [ModuleService::class, "createAction"],
			"permission" => ["level" => 2],
			"body" => [
				"name" => "required|string|max:255",
				"route" => "string|max:127",
				"in_nav" => "bool",
				"icon" => "string|max:64",
				"class" => "string|max:64",
				"form" => "int|min:0",
				"view" => "int|min:0",
				"report" => "int|min:0",
				"level" => "int|in:0,1,2",
				"position" => "int|min:0",
			],
			"audit" => ["table" => "module-actions", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}/actions/{sid:int}" => [
			"service" => [ModuleService::class, "updateAction"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-actions", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id:int}/actions/{sid:int}" => [
			"service" => [ModuleService::class, "deleteAction"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-actions", "type" => "deleted", "entry" => "%sid%"],
		],
		"POST /modules/{id:int}/actions/reorder" => [
			"service" => [ModuleService::class, "reorderActions"],
			"permission" => ["level" => 2],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "module-actions", "type" => "reordered", "entry" => "%id%"],
		],

		"GET /modules/{id:int}/forms" => ["service" => [ModuleService::class, "forms"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id:int}/forms" => [
			"service" => [ModuleService::class, "createForm"],
			"permission" => ["level" => 2],
			"body" => [
				"title" => "required|string|max:255",
				"table" => "required|string|max:255",
				"fields" => "array",
				"default_position" => "string|max:64",
				"return_view" => "int|min:0",
				"return_url" => "string|max:1024",
				"tagging" => "bool",
				"open_graph" => "bool",
				"hooks" => "array",
			],
			"audit" => ["table" => "module-forms", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}/forms/{sid:int}" => [
			"service" => [ModuleService::class, "updateForm"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-forms", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id:int}/forms/{sid:int}" => [
			"service" => [ModuleService::class, "deleteForm"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-forms", "type" => "deleted", "entry" => "%sid%"],
		],

		"GET /modules/{id:int}/views" => ["service" => [ModuleService::class, "views"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id:int}/views" => [
			"service" => [ModuleService::class, "createView"],
			"permission" => ["level" => 2],
			"body" => [
				"title" => "required|string|max:255",
				"description" => "string|max:1024",
				"table" => "required|string|max:255",
				"type" => "string|max:64",
				"settings" => "array",
				"fields" => "array",
				"actions" => "array",
				"related_form" => "int|min:0",
				"preview_url" => "string|max:1024",
				"exclude_from_search" => "bool",
			],
			"audit" => ["table" => "module-views", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}/views/{sid:int}" => [
			"service" => [ModuleService::class, "updateView"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-views", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id:int}/views/{sid:int}" => [
			"service" => [ModuleService::class, "deleteView"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-views", "type" => "deleted", "entry" => "%sid%"],
		],

		"GET /modules/{id:int}/reports" => ["service" => [ModuleService::class, "reports"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id:int}/reports" => [
			"service" => [ModuleService::class, "createReport"],
			"permission" => ["level" => 2],
			"body" => [
				"title" => "required|string|max:255",
				"table" => "required|string|max:255",
				"type" => "string|in:csv,view",
				"filters" => "array",
				"fields" => "array",
				"parser" => "string|max:255",
				"view" => "int|min:0",
				"streaming" => "bool",
			],
			"audit" => ["table" => "module-reports", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}/reports/{sid:int}" => [
			"service" => [ModuleService::class, "updateReport"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-reports", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id:int}/reports/{sid:int}" => [
			"service" => [ModuleService::class, "deleteReport"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-reports", "type" => "deleted", "entry" => "%sid%"],
		],

		"GET /modules/{id:int}/embed-forms" => ["service" => [ModuleService::class, "embedForms"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id:int}/embed-forms" => [
			"service" => [ModuleService::class, "createEmbedForm"],
			"permission" => ["level" => 2],
			"body" => [
				"title" => "required|string|max:255",
				"table" => "required|string|max:255",
				"fields" => "array",
				"hooks" => "array",
				"default_position" => "string|max:64",
				"default_pending" => "bool",
				"css" => "string|max:1024",
				"redirect_url" => "string|max:1024",
				"thank_you_message" => "string",
			],
			"audit" => ["table" => "module-embed-forms", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}/embed-forms/{sid:int}" => [
			"service" => [ModuleService::class, "updateEmbedForm"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-embed-forms", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id:int}/embed-forms/{sid:int}" => [
			"service" => [ModuleService::class, "deleteEmbedForm"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-embed-forms", "type" => "deleted", "entry" => "%sid%"],
		],

		"GET /module-groups" => ["service" => [ModuleService::class, "listGroups"], "permission" => ["level" => 0]],
		"GET /module-groups/{id:int}" => ["service" => [ModuleService::class, "getGroup"], "permission" => ["level" => 0]],
		"POST /module-groups" => [
			"service" => [ModuleService::class, "createGroup"],
			"permission" => ["level" => 2],
			"body" => ["name" => "required|string|max:255", "route" => "string|max:127"],
			"audit" => ["table" => "module-groups", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /module-groups/{id:int}" => [
			"service" => [ModuleService::class, "updateGroup"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-groups", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /module-groups/{id:int}" => [
			"service" => [ModuleService::class, "deleteGroup"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-groups", "type" => "deleted", "entry" => "%id%"],
		],
	];
