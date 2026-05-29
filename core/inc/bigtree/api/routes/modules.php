<?php
	use BigTree\Services\ModuleService;

	return [
		"GET /modules" => ["service" => [ModuleService::class, "list"], "permission" => ["level" => 0]],
		"GET /modules/{id}" => [
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
		"PATCH /modules/{id}" => [
			"service" => [ModuleService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "modules", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /modules/{id}" => [
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

		"GET /modules/{id}/actions" => ["service" => [ModuleService::class, "actions"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id}/actions" => [
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
		"PATCH /modules/{id}/actions/{sid}" => [
			"service" => [ModuleService::class, "updateAction"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-actions", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id}/actions/{sid}" => [
			"service" => [ModuleService::class, "deleteAction"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-actions", "type" => "deleted", "entry" => "%sid%"],
		],
		"POST /modules/{id}/actions/reorder" => [
			"service" => [ModuleService::class, "reorderActions"],
			"permission" => ["level" => 2],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "module-actions", "type" => "reordered", "entry" => "%id%"],
		],

		"GET /modules/{id}/forms" => ["service" => [ModuleService::class, "forms"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id}/forms" => [
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
		"PATCH /modules/{id}/forms/{sid}" => [
			"service" => [ModuleService::class, "updateForm"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-forms", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id}/forms/{sid}" => [
			"service" => [ModuleService::class, "deleteForm"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-forms", "type" => "deleted", "entry" => "%sid%"],
		],

		// Relation-options lookup powers OneToManyField / ManyToManyField in the
		// SPA. Read-only; gated by view access on the module so editors can fill
		// in relation pickers without needing publisher rights.
		"GET /modules/{id}/forms/{sid}/relation-options" => [
			"service" => [ModuleService::class, "relationOptions"],
			"permission" => ["module" => "%id%", "min" => "v"],
		],

		"GET /modules/{id}/views" => ["service" => [ModuleService::class, "views"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id}/views" => [
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
		"PATCH /modules/{id}/views/{sid}" => [
			"service" => [ModuleService::class, "updateView"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-views", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id}/views/{sid}" => [
			"service" => [ModuleService::class, "deleteView"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-views", "type" => "deleted", "entry" => "%sid%"],
		],

		"GET /modules/{id}/reports" => ["service" => [ModuleService::class, "reports"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id}/reports" => [
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
		"PATCH /modules/{id}/reports/{sid}" => [
			"service" => [ModuleService::class, "updateReport"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-reports", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id}/reports/{sid}" => [
			"service" => [ModuleService::class, "deleteReport"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-reports", "type" => "deleted", "entry" => "%sid%"],
		],
		"GET /modules/{id}/reports/{sid}/prepare" => [
			"service" => [ModuleService::class, "prepareReport"],
			"permission" => ["module" => "%id%", "min" => "v"],
		],
		"POST /modules/{id}/reports/{sid}/run" => [
			"service" => [ModuleService::class, "runReport"],
			"permission" => ["module" => "%id%", "min" => "v"],
			"body" => ["filters" => "array", "sort" => "array"],
		],

		// Category list for a GBP module — consumed by the user editor's module
		// permission tree, so gated at admin (level 1) rather than module view.
		"GET /modules/{id}/gbp-categories" => [
			"service" => [ModuleService::class, "gbpCategories"],
			"permission" => ["level" => 1],
		],

		"GET /modules/{id}/embed-forms" => ["service" => [ModuleService::class, "embedForms"], "permission" => ["module" => "%id%", "min" => "v"]],
		"POST /modules/{id}/embed-forms" => [
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
		"PATCH /modules/{id}/embed-forms/{sid}" => [
			"service" => [ModuleService::class, "updateEmbedForm"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-embed-forms", "type" => "updated", "entry" => "%sid%"],
		],
		"DELETE /modules/{id}/embed-forms/{sid}" => [
			"service" => [ModuleService::class, "deleteEmbedForm"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-embed-forms", "type" => "deleted", "entry" => "%sid%"],
		],

		// — Public embed form routes — no auth required so the form can be
		// loaded and submitted from a third-party page via the SPA's
		// /embed/:hash route.
		"GET /embed-forms/{hash}" => [
			"service" => [ModuleService::class, "publicGetEmbedForm"],
			"permission" => "public",
		],
		"POST /embed-forms/{hash}/submit" => [
			"service" => [ModuleService::class, "publicSubmitEmbedForm"],
			"permission" => "public",
			"body" => ["values" => "array"],
		],

		"GET /module-groups" => ["service" => [ModuleService::class, "listGroups"], "permission" => ["level" => 0]],
		"GET /module-groups/{id}" => ["service" => [ModuleService::class, "getGroup"], "permission" => ["level" => 0]],
		"POST /module-groups" => [
			"service" => [ModuleService::class, "createGroup"],
			"permission" => ["level" => 2],
			"body" => ["name" => "required|string|max:255", "route" => "string|max:127"],
			"audit" => ["table" => "module-groups", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /module-groups/{id}" => [
			"service" => [ModuleService::class, "updateGroup"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-groups", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /module-groups/{id}" => [
			"service" => [ModuleService::class, "deleteGroup"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-groups", "type" => "deleted", "entry" => "%id%"],
		],
	];
