<?php
	/*
		API Call: /indexeddb/index/
			Returns an index of all URLs required to build the IndexedDB cache of data.
	*/
	
	namespace BigTree;
	
	/*
	 	Function: indexeddb/schema
			Returns the schema layout needed for an IndexedDB instance for cached view data.
		
		Method: GET
	 	
		Returns:
			An array of schemas
	*/
	
	$schema = [
		"pages" => [
			"columns" => [
				"id",
				"parent",
				"title",
				"status",
				"archived",
				"in_nav",
				"position"
			],
			"indexes" => [
				"parent",
				"in_nav",
				"position",
				"archived"
			],
			"key" => "id"
		],
		"settings" => [
			"columns" => [
				"id",
				"title",
				"value"
			],
			"indexes" => [
				"title"
			],
			"key" => "id"
		],
		"users" => [
			"columns" => [
				"id",
				"name",
				"email",
				"company",
				"level"
			],
			"indexes" => [
				"name",
				"email",
				"company",
				"level"
			],
			"key" => "id"
		],
		"files" => [
			"columns" => [
				"id",
				"folder",
				"title",
				"type",
				"size",
				"image"
			],
			"indexes" => [
				"folder",
				"title",
				"type"
			],
			"key" => "id"
		],
		"tags" => [
			"columns" => [
				"id",
				"tag",
				"usage_count"
			],
			"indexes" => [
				"tag",
				"usage_count"
			],
			"key" => "id"
		],
		"module_groups" => [
			"columns" => [
				"id",
				"title",
				"position"
			],
			"indexes" => [
				"position"
			],
			"key" => "id"
		],
		"modules" => [
			"columns" => [
				"id",
				"group",
				"title",
				"position",
				"actions"
			],
			"indexes" => [
				"group",
				"position"
			],
			"key" => "id"
		]
	];
	
	$modules = DB::getAll("modules");
	
	foreach ($modules as $module) {
		foreach ($module["interfaces"] as $interface) {
			if ($interface["type"] == "view") {
				$view_schema = [
					"columns" => [
						"id",
						"gbp_field",
						"published_gbp_field",
						"group_field",
						"sort_field",
						"group_sort_field",
						"position",
						"archived",
						"featured",
						"status",
						"pending_owner"
					],
					"indexes" => [
						"group_field",
						"sort_field",
						"group_sort_field",
						"position"
					],
					"key" => "id"
				];
				
				for ($x = 1; $x <= count($interface["settings"]["fields"]); $x++) {
					$view_schema["columns"][] = "column".$x;
				}
				
				$schema[$module["id"]."-".$interface["id"]] = $view_schema;
			}
		}
	}
	
	API::sendResponse($schema);
	