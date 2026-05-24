<?php
	use BigTree\Services\FieldTypeService;

	return [
		"GET /field-types" => [
			"service" => [FieldTypeService::class, "list"],
			"permission" => ["level" => 0],
			"query" => ["split" => "bool"],
		],
		"GET /field-types/{id}" => [
			"service" => [FieldTypeService::class, "get"],
			"permission" => ["level" => 0],
		],
		"POST /field-types" => [
			"service" => [FieldTypeService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"id" => "required|string|max:127",
				"name" => "string|max:255",
				"use_cases" => "array",
				"self_draw" => "bool",
			],
			"audit" => ["table" => "field-types", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /field-types/{id}" => [
			"service" => [FieldTypeService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "field-types", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /field-types/{id}" => [
			"service" => [FieldTypeService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "field-types", "type" => "deleted", "entry" => "%id%"],
		],

		"GET /field-types/{id}/schema" => [
			"service" => [FieldTypeService::class, "schema"],
			"permission" => ["level" => 0],
		],

		"POST /field-types/{id}/render" => [
			"service" => [FieldTypeService::class, "render"],
			"permission" => ["level" => 0],
			"body" => ["field" => "array"],
		],
	];
