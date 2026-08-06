<?php
	/**
	 * ModuleFormService (plan 029, phase B) owns the module form cluster pulled
	 * out of ModuleService: CRUD over form sub-resources stored in a module's
	 * JSONDB record, plus the relation-options and list-options lookups that power
	 * the SPA's relation pickers and dynamic list/select fields.
	 *
	 * Coverage map (reported in the plan's handoff):
	 *   - createForm        — UNIT (seed module, assert insert + 201 envelope +
	 *                         persistence, fields passed through cleanFormFields)
	 *   - updateForm        — UNIT (assert patch of supplied fields, others left
	 *                         untouched; 200 envelope; 404 path)
	 *   - deleteForm        — UNIT (assert removal + cascade delete of actions
	 *                         referencing the form — see deleteForm's loop; 404)
	 *   - relationOptions   — UNIT (validation/404 paths via store-only seeding:
	 *                         missing column param, unknown form, unknown field,
	 *                         non-relation field type); the DB happy-path is
	 *                         exercised against a throwaway table when SQL is up.
	 *   - listOptions       — UNIT (static-list happy path needs no DB; state list
	 *                         happy path; validation/404 paths; db list-type
	 *                         against a throwaway table when SQL is up)
	 *   - listOptionsFromSettings — UNIT (settings-only path for page templates /
	 *                         declarative fields; static + state + missing body;
	 *                         db against throwaway table when SQL is up)
	 *
	 * Tests seed a throwaway module (and, for the db-backed paths, a throwaway
	 * table) and clean everything up in a finally block. Skips on an unavailable
	 * store/DB.
	 */

	use BigTree\Services\ModuleFormService;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;

	/** Build a Request with the given route params, JSON body and query. */
	function _form_request(array $route_params, array $body = [], array $query = []): Request {
		$req = new Request();
		$req->method = "POST";
		$req->route_params = $route_params;
		$req->body = $body;
		$req->query = $query;

		return $req;
	}

	/** True when the modules JSONDB store is reachable in this harness. */
	function _form_store_ready(): bool {
		try {
			BigTreeJSONDB::getAll("modules");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — modules store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** True when a live SQL connection is available. */
	function _form_sql_ready(): bool {
		try {
			SQL::fetchSingle("SELECT 1");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** Re-read a module's forms straight from the store (bypassing any cache). */
	function _form_reload_forms(string $module_id): array {
		BigTreeJSONDB::$Cache = [];
		$module = BigTreeJSONDB::get("modules", $module_id);

		return is_array($module["forms"] ?? null) ? $module["forms"] : [];
	}

	function test_form_create_inserts_and_returns_envelope() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
			]);

			$service = new ModuleFormService();
			$response = $service->createForm(_form_request(
				["id" => $module_id],
				[
					"title" => "My Form",
					"table" => "",
					"fields" => [
						// Mixed shape on purpose — confirms cleanFormFields ran:
						// a junk entry is dropped, a column-less numeric entry is
						// dropped, the type defaults to text, and settings normalize.
						["column" => "headline", "title" => "Headline"],
						"not-an-array",
						["title" => "no column here"],
					],
				]
			));

			T::ok($response instanceof Response, "createForm returns a Response");
			T::equals($response->status, 201, "createForm responds 201 Created");

			$created = $response->body["data"];
			T::equals($created["title"], "My Form", "created form carries the submitted title");
			T::ok(!empty($created["id"]), "created form has an id assigned");
			T::equals(count($created["fields"]), 1, "cleanFormFields dropped junk + column-less fields");
			T::equals($created["fields"][0]["column"], "headline", "the one valid field survived cleaning");
			T::equals($created["fields"][0]["type"], "text", "cleanFormFields defaulted the field type to text");

			$persisted = _form_reload_forms($module_id);
			T::equals(count($persisted), 1, "exactly one form persisted to the module store");
			T::equals($persisted[0]["id"], $created["id"], "persisted form id matches the returned id");
			T::equals(count($persisted[0]["fields"]), 1, "persisted form stored the cleaned field list");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_update_patches_supplied_fields() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					["id" => "form-up", "title" => "Before", "table" => "btx_keep", "fields" => []],
				],
			]);

			$service = new ModuleFormService();
			$response = $service->updateForm(_form_request(
				["id" => $module_id, "sid" => "form-up"],
				["title" => "After"]
			));

			T::equals($response->status, 200, "updateForm responds 200 OK");

			$updated = $response->body["data"];
			T::equals($updated["title"], "After", "updateForm patched the title");
			T::equals($updated["table"], "btx_keep", "updateForm left the un-submitted table untouched");

			$persisted = _form_reload_forms($module_id);
			T::equals($persisted[0]["title"], "After", "patched title persisted to the store");
			T::equals($persisted[0]["table"], "btx_keep", "untouched table persisted unchanged");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_update_missing_throws_not_found() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
			]);

			$service = new ModuleFormService();

			T::throws(function () use ($service, $module_id) {
				$service->updateForm(_form_request(
					["id" => $module_id, "sid" => "no-such-form"],
					["title" => "x"]
				));
			}, NotFoundException::class, "updateForm throws NotFound for an absent form");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_delete_removes_form_and_cascades_actions() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					["id" => "form-del", "title" => "Doomed"],
					["id" => "form-keep", "title" => "Survivor"],
				],
				"actions" => [
					["id" => "act-on-doomed", "form" => "form-del", "title" => "Edit Doomed"],
					["id" => "act-on-keep", "form" => "form-keep", "title" => "Edit Survivor"],
					["id" => "act-no-form", "title" => "Standalone"],
				],
			]);

			$service = new ModuleFormService();
			$response = $service->deleteForm(_form_request(["id" => $module_id, "sid" => "form-del"]));

			T::equals($response->status, 204, "deleteForm responds 204 No Content");

			BigTreeJSONDB::$Cache = [];
			$module = BigTreeJSONDB::get("modules", $module_id);
			$form_ids = array_map(function ($f) {
				return $f["id"];
			}, $module["forms"] ?? []);
			$action_ids = array_map(function ($a) {
				return $a["id"];
			}, $module["actions"] ?? []);

			T::ok(!in_array("form-del", $form_ids, true), "deleted form is gone from the store");
			T::ok(in_array("form-keep", $form_ids, true), "unrelated form survives the delete");
			T::ok(!in_array("act-on-doomed", $action_ids, true), "action referencing the deleted form cascaded away");
			T::ok(in_array("act-on-keep", $action_ids, true), "action referencing a surviving form is untouched");
			T::ok(in_array("act-no-form", $action_ids, true), "action with no form reference is untouched");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_delete_missing_throws_not_found() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
			]);

			$service = new ModuleFormService();

			T::throws(function () use ($service, $module_id) {
				$service->deleteForm(_form_request(["id" => $module_id, "sid" => "no-such-form"]));
			}, NotFoundException::class, "deleteForm throws NotFound for an absent form");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_list_options_static_list() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					[
						"id" => "form-list",
						"title" => "Listy",
						"fields" => [
							[
								"column" => "color",
								"type" => "list",
								"settings" => [
									"list_type" => "static",
									"list" => [
										["key" => "r", "description" => "Red"],
										["key" => "g", "description" => "Green"],
									],
								],
							],
						],
					],
				],
			]);

			$service = new ModuleFormService();
			$response = $service->listOptions(_form_request(
				["id" => $module_id, "sid" => "form-list"],
				[],
				["column" => "color"]
			));

			T::equals($response->status, 200, "listOptions responds 200 OK for a static list");
			$options = $response->body["data"]["options"];
			T::equals(count($options), 2, "static list returns one option per item");
			T::equals($options[0], ["value" => "r", "label" => "Red"], "static option maps key->value, description->label");
			T::equals($options[1], ["value" => "g", "label" => "Green"], "second static option mapped correctly");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_list_options_state_list() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					[
						"id" => "form-state",
						"title" => "Stately",
						"fields" => [
							["column" => "st", "type" => "list", "settings" => ["list_type" => "state"]],
						],
					],
				],
			]);

			$service = new ModuleFormService();
			$response = $service->listOptions(_form_request(
				["id" => $module_id, "sid" => "form-state"],
				[],
				["column" => "st"]
			));

			$options = $response->body["data"]["options"];
			T::ok(count($options) > 0, "state list returns the built-in StateList options");
			T::ok(isset($options[0]["value"]) && isset($options[0]["label"]), "state options have value + label keys");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_list_options_missing_column_param_throws() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
			]);

			$service = new ModuleFormService();

			T::throws(function () use ($service, $module_id) {
				$service->listOptions(_form_request(["id" => $module_id, "sid" => "x"], [], []));
			}, BadRequestException::class, "listOptions throws BadRequest when ?column is missing");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_list_options_non_list_field_throws() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					[
						"id" => "form-nl",
						"fields" => [["column" => "name", "type" => "text"]],
					],
				],
			]);

			$service = new ModuleFormService();

			T::throws(function () use ($service, $module_id) {
				$service->listOptions(_form_request(
					["id" => $module_id, "sid" => "form-nl"],
					[],
					["column" => "name"]
				));
			}, BadRequestException::class, "listOptions throws BadRequest when the field is not a list");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_list_options_db_list_against_table() {
		if (!_form_store_ready() || !_form_sql_ready()) {
			return;
		}

		$table = "btx_list_" . substr(md5(uniqid()), 0, 8);
		$module_id = null;

		try {
			SQL::query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64))");
			SQL::query("INSERT INTO `$table` (name) VALUES ('Alpha'), ('Beta')");

			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					[
						"id" => "form-db",
						"fields" => [
							[
								"column" => "ref",
								"type" => "list",
								"settings" => [
									"list_type" => "db",
									"pop-table" => $table,
									"pop-description" => "name",
									"pop-sort" => "name ASC",
								],
							],
						],
					],
				],
			]);

			$service = new ModuleFormService();
			$response = $service->listOptions(_form_request(
				["id" => $module_id, "sid" => "form-db"],
				[],
				["column" => "ref"]
			));

			$options = $response->body["data"]["options"];
			T::equals(count($options), 2, "db list returns one option per table row");
			T::equals($options[0]["label"], "Alpha", "db options ordered ascending by descriptor");
			T::equals($options[1]["label"], "Beta", "second db option mapped correctly");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			try {
				SQL::query("DROP TABLE IF EXISTS `$table`");
			} catch (\Throwable $e) {
				// best-effort cleanup
			}
		}
	}

	/**
	 * Settings-only list options — the path SelectField uses outside module forms
	 * (page templates, declarative custom fields like Form Builder's form picker).
	 */
	function test_form_list_options_from_settings_static() {
		$service = new ModuleFormService();
		$response = $service->listOptionsFromSettings(_form_request([], [
			"settings" => [
				"list_type" => "static",
				"list" => [
					["key" => "a", "description" => "Alpha"],
					["value" => "b", "label" => "Beta"],
				],
			],
			"column" => "form",
		]));

		T::equals($response->status, 200, "listOptionsFromSettings responds 200 for a static list");
		$options = $response->body["data"]["options"];
		T::equals(count($options), 2, "settings static list returns both options");
		T::equals($options[0], ["value" => "a", "label" => "Alpha"], "key/description mapped");
		T::equals($options[1], ["value" => "b", "label" => "Beta"], "value/label mapped");
	}

	function test_form_list_options_from_settings_state() {
		$service = new ModuleFormService();
		$response = $service->listOptionsFromSettings(_form_request([], [
			"settings" => ["list_type" => "state"],
		]));

		$options = $response->body["data"]["options"];
		T::ok(count($options) > 0, "settings state list returns built-in options");
		T::ok(isset($options[0]["value"]) && isset($options[0]["label"]), "state options have value + label");
	}

	function test_form_list_options_from_settings_missing_settings_throws() {
		$service = new ModuleFormService();

		T::throws(function () use ($service) {
			$service->listOptionsFromSettings(_form_request([], []));
		}, BadRequestException::class, "listOptionsFromSettings throws when settings are missing");
	}

	function test_form_list_options_from_settings_db_list() {
		if (!_form_sql_ready()) {
			return;
		}

		$table = "btx_list_set_" . substr(md5(uniqid()), 0, 8);

		try {
			SQL::query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(64))");
			SQL::query("INSERT INTO `$table` (title) VALUES ('Contact'), ('Newsletter')");

			$service = new ModuleFormService();
			$response = $service->listOptionsFromSettings(_form_request([], [
				"settings" => [
					"list_type" => "db",
					"pop-table" => $table,
					"pop-description" => "title",
					"pop-sort" => "title ASC",
				],
				"column" => "form",
			]));

			$options = $response->body["data"]["options"];
			T::equals(count($options), 2, "settings db list returns one option per row");
			T::equals($options[0]["label"], "Contact", "first db option label");
			T::equals($options[1]["label"], "Newsletter", "second db option label");
		} finally {
			try {
				SQL::query("DROP TABLE IF EXISTS `$table`");
			} catch (\Throwable $e) {
				// best-effort cleanup
			}
		}
	}

	function test_form_relation_options_non_relation_field_throws() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					[
						"id" => "form-rel",
						"fields" => [["column" => "name", "type" => "text"]],
					],
				],
			]);

			$service = new ModuleFormService();

			T::throws(function () use ($service, $module_id) {
				$service->relationOptions(_form_request(
					["id" => $module_id, "sid" => "form-rel"],
					[],
					["column" => "name"]
				));
			}, BadRequestException::class, "relationOptions throws BadRequest when the field is not a relation field");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_relation_options_unknown_field_throws() {
		if (!_form_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [["id" => "form-rel2", "fields" => []]],
			]);

			$service = new ModuleFormService();

			T::throws(function () use ($service, $module_id) {
				$service->relationOptions(_form_request(
					["id" => $module_id, "sid" => "form-rel2"],
					[],
					["column" => "nope"]
				));
			}, NotFoundException::class, "relationOptions throws NotFound for a column not in the form");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_form_relation_options_one_to_many_against_table() {
		if (!_form_store_ready() || !_form_sql_ready()) {
			return;
		}

		$table = "btx_rel_" . substr(md5(uniqid()), 0, 8);
		$module_id = null;

		try {
			SQL::query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(64))");
			SQL::query("INSERT INTO `$table` (title) VALUES ('Aardvark'), ('Zebra')");

			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_formsvc_" . uniqid(),
				"route" => "zz-formsvc-" . uniqid(),
				"forms" => [
					[
						"id" => "form-otm",
						"fields" => [
							[
								"column" => "things",
								"type" => "one-to-many",
								"settings" => [
									"table" => $table,
									"title_column" => "title",
									"sort_by_column" => "title ASC",
								],
							],
						],
					],
				],
			]);

			$service = new ModuleFormService();
			$response = $service->relationOptions(_form_request(
				["id" => $module_id, "sid" => "form-otm"],
				[],
				["column" => "things"]
			));

			T::equals($response->status, 200, "relationOptions responds 200 OK for a one-to-many field");
			$data = $response->body["data"];
			T::equals($data["relation"]["type"], "one-to-many", "relation type reported as one-to-many");
			T::equals(count($data["items"]), 2, "relationOptions returns one item per related row");
			T::equals($data["items"][0]["title"], "Aardvark", "items ordered ascending by descriptor");
			T::equals($data["items"][1]["title"], "Zebra", "second related item mapped correctly");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			try {
				SQL::query("DROP TABLE IF EXISTS `$table`");
			} catch (\Throwable $e) {
				// best-effort cleanup
			}
		}
	}
