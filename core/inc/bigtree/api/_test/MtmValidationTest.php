<?php
	use BigTree\Services\AutoModuleService;
	use BigTree\Api\Exceptions\BadRequestException;

	function test_mtm_rejects_undeclared_triple() {
		$svc = new AutoModuleService();
		$ref = new ReflectionMethod($svc, "validateMtm");
		$ref->setAccessible(true);

		$module = ["forms" => [[
			"table" => "btx_things",
			"fields" => [[
				"type" => "many-to-many",
				"settings" => [
					"mtm-connecting-table" => "btx_things_to_cats",
					"mtm-my-id" => "thing",
					"mtm-other-id" => "cat",
				],
			]],
		]]];

		// Declared triple passes through unchanged.
		$declared = [[
			"table" => "btx_things_to_cats",
			"my-id" => "thing",
			"other-id" => "cat",
			"data" => [1, 2, 3],
		]];
		$out = $ref->invoke($svc, $module, "btx_things", $declared);
		T::equals(count($out), 1, "declared mtm triple is kept");

		// Forged table → rejected.
		T::throws(function () use ($ref, $svc, $module) {
			$ref->invoke($svc, $module, "btx_things", [[
				"table" => "bigtree_users` (x) VALUES ('1','2'); -- ",
				"my-id" => "thing",
				"other-id" => "cat",
				"data" => [1],
			]]);
		}, BadRequestException::class, "forged mtm table is rejected");

		// Forged column → rejected.
		T::throws(function () use ($ref, $svc, $module) {
			$ref->invoke($svc, $module, "btx_things", [[
				"table" => "btx_things_to_cats",
				"my-id" => "thing`,`evil",
				"other-id" => "cat",
				"data" => [1],
			]]);
		}, BadRequestException::class, "forged mtm column is rejected");
	}

	function test_mtm_empty_is_noop() {
		$svc = new AutoModuleService();
		$ref = new ReflectionMethod($svc, "validateMtm");
		$ref->setAccessible(true);
		T::equals($ref->invoke($svc, [], "btx_things", []), [], "empty mtm returns empty");
	}
