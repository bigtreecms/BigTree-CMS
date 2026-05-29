<?php
	use BigTree\Services\FourOhFourService;

	return [
		"GET /404s" => [
			"service" => [FourOhFourService::class, "list"],
			"permission" => ["level" => 1],
			"query" => [
				"page" => "int|min:1", "per_page" => "int|min:1|max:100",
				"type" => "string|in:404,301,ignored",
				"site_key" => "string|max:255",
				"q" => "string|max:200",
			],
		],
		"POST /404s" => [
			"service" => [FourOhFourService::class, "create"],
			"permission" => ["level" => 1],
			"body" => ["from" => "required|string|max:1024", "to" => "required|string|max:1024", "site_key" => "string|max:255"],
			"audit" => ["table" => "bigtree_404s", "type" => "created", "entry" => "%id%"],
		],
		"DELETE /404s/{id:int}" => [
			"service" => [FourOhFourService::class, "delete"],
			"permission" => ["level" => 1],
			"audit" => ["table" => "bigtree_404s", "type" => "deleted", "entry" => "%id%"],
		],
		"POST /404s/{id:int}/redirect" => [
			"service" => [FourOhFourService::class, "setRedirect"],
			"permission" => ["level" => 1],
			"body" => ["url" => "required|string|max:1024"],
			"audit" => ["table" => "bigtree_404s", "type" => "redirect_set", "entry" => "%id%"],
		],
		"POST /404s/{id:int}/ignore" => [
			"service" => [FourOhFourService::class, "ignore"],
			"permission" => ["level" => 1],
			"audit" => ["table" => "bigtree_404s", "type" => "ignored", "entry" => "%id%"],
		],
		"POST /404s/clear-dead" => [
			"service" => [FourOhFourService::class, "clearDead"],
			"permission" => ["level" => 1],
			"audit" => ["table" => "bigtree_404s", "type" => "clear_dead", "entry" => "0"],
		],
		"POST /404s/bulk-delete" => [
			"service" => [FourOhFourService::class, "bulkDelete"],
			"permission" => ["level" => 1],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "bigtree_404s", "type" => "bulk_deleted", "entry" => "0"],
		],
		"POST /404s/import" => [
			"service" => [FourOhFourService::class, "importCsv"],
			"permission" => ["level" => 1],
			"multipart" => true,
			"audit" => ["table" => "bigtree_404s", "type" => "imported", "entry" => "0"],
		],
	];
