<?php
	use BigTree\Services\TagService;

	return [
		"GET /tags" => [
			"service" => [TagService::class, "list"],
			"permission" => ["level" => 0],
			"query" => ["page" => "int|min:1", "per_page" => "int|min:1|max:100", "q" => "string|max:200"],
		],

		"GET /tags/search" => [
			"service" => [TagService::class, "search"],
			"permission" => ["level" => 0],
			"query" => ["q" => "required|string|max:200"],
		],

		// Level 0 on purpose: the legacy tag browser lets any editor create tags
		// inline from page/module forms (ajax/tags/create-tag.php has no gate).
		// Destructive actions (delete/merge) stay admin-only below.
		"POST /tags" => [
			"service" => [TagService::class, "create"],
			"permission" => ["level" => 0],
			"body" => ["tag" => "required|string|max:255"],
			"audit" => ["table" => "bigtree_tags", "type" => "created", "entry" => "%id%"],
		],

		"GET /tags/{id:int}" => [
			"service" => [TagService::class, "get"],
			"permission" => ["level" => 0],
		],

		"DELETE /tags/{id:int}" => [
			"service" => [TagService::class, "delete"],
			"permission" => ["level" => 1],
			"audit" => ["table" => "bigtree_tags", "type" => "deleted", "entry" => "%id%"],
		],

		"POST /tags/merge" => [
			"service" => [TagService::class, "merge"],
			"permission" => ["level" => 1],
			"body" => ["into" => "required|int", "from" => "required|array"],
			"audit" => ["table" => "bigtree_tags", "type" => "merged", "entry" => "%into%"],
		],
	];
