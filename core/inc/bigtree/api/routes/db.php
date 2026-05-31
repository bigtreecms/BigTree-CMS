<?php
	use BigTree\Services\DbService;

	return [
		// Table / column introspection for the field-settings designer controls
		// (db_table, db_column, db_column_sort). Developer-gated.
		"GET /db/tables" => [
			"service" => [DbService::class, "tables"],
			"permission" => ["level" => 2],
		],
		"GET /db/tables/{table}/columns" => [
			"service" => [DbService::class, "columns"],
			"permission" => ["level" => 2],
			"query" => ["sort" => "bool"],
		],
	];
