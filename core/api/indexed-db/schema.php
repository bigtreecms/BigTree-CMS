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
	
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			A status indicator of whether the schema has changed.
			If changed (or not passing since) the schema is also returned.
	*/
	
	if ($_GET["since"]) {
		$since = date("Y-m-d H:i:s", strtotime($_GET["since"]));
		$updated = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_audit_trail
									 WHERE `table` = 'config:schema' AND `date` >= ? AND `type` = 'update'", $since);
		
		if (!$updated) {
			API::sendResponse(["status" => "unchanged"]);
		}
	}
	
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
				"name",
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
				"position",
				"route"
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
				"name",
				"position",
				"actions",
				"route"
			],
			"indexes" => [
				"group",
				"position"
			],
			"key" => "id"
		],
		"module-view-cache" => [
			"columns" => [
				"key",
				"view",
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
				"view",
				"id",
				"group_field",
				"sort_field",
				"group_sort_field",
				"position"
			],
			"key" => "key"
		]
	];
	
	API::sendResponse([
		"status" => !empty($_GET["since"]) ? "changed" : "new",
		"schema" => $schema
	]);
	