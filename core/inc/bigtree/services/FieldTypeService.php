<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeJSONDB;
	use BigTree;

	/**
	 * Field types: built-in + custom registry. The SPA's most-pulled endpoint
	 * because every form-rendering call needs it. ETag'd against the JSONDB file
	 * mtime so unchanged registries are served as 304.
	 */
	class FieldTypeService {
		public function list(Request $request) {
			// Hash against the live JSONDB file BigTreeJSONDB actually reads/writes
			// (custom/json-db/field-types.json) — not the install-time setup seed,
			// which doesn't exist here and would yield a constant ETag that never
			// invalidates after a create/update/delete. The built-in list is
			// hardcoded in getCachedFieldTypes(), so only this file's mtime matters.
			$paths = array_filter([
				file_exists(SERVER_ROOT . "custom/json-db/field-types.json") ? SERVER_ROOT . "custom/json-db/field-types.json" : null,
			]);
			$etag = ETag::fromMtimes($paths);

			if (ETag::check($request, $etag)) {
				$r = Response::raw(304, []); $r->is_envelope = false; $r->body = null;
				$r->header("ETag", $etag);
				$r->header("Cache-Control", "private, no-cache");

				return $r;
			}

			$split = !empty($request->query["split"]);

			// getCachedFieldTypes() returns data nested by use_case for the legacy
			// admin. Flatten into the SPA-friendly shape: Record<typeId, FieldType>
			// (or {default, custom} of the same) with use_cases as an array.
			$nested = BigTreeAdmin::getCachedFieldTypes(true);
			$entries = [];

			foreach ($nested as $use_case => $buckets) {
				foreach (["default", "custom"] as $bucket) {
					if (empty($buckets[$bucket]) || !is_array($buckets[$bucket])) {
						continue;
					}

					foreach ($buckets[$bucket] as $id => $info) {
						if (!isset($entries[$id])) {
							$entries[$id] = [
								"id" => (string)$id,
								"name" => isset($info["name"]) ? (string)$info["name"] : (string)$id,
								"self_draw" => !empty($info["self_draw"]),
								"use_cases" => [],
								"_bucket" => $bucket,
							];
						}

						$entries[$id]["use_cases"][] = $use_case;
					}
				}
			}

			// Enrich each entry with the render contract the SPA needs to decide how
			// to draw the input: "core-component" (built-in React), "declarative"
			// (input_schema composed from primitives), "module" (sandboxed JS), or
			// "server" (POST /field-types/{id}/render bridge). trust gates in-context
			// vs sandbox execution (see spa/.custom-field-types-design.md).
			$schemas = $this->loadSchemas();

			foreach ($entries as $id => &$entry) {
				$schema = isset($schemas[$id]) && is_array($schemas[$id]) ? $schemas[$id] : null;
				$record = $schema === null ? BigTreeJSONDB::get("field-types", $id) : null;
				$source = $schema ?? (is_array($record) ? $record : []);

				$entry["render"] = $this->renderKind($source, $entry["_bucket"]);
				$entry["value_type"] = isset($source["value_type"]) ? (string)$source["value_type"] : "string";
				$entry["contract_version"] = (int)($source["contract_version"] ?? 1);
				$entry["trust"] = isset($source["trust"])
					? (string)$source["trust"]
					: ($entry["_bucket"] === "default" ? "core" : "marketplace");

				if (!empty($source["asset_url"])) {
					$entry["asset_url"] = (string)$source["asset_url"];
				}
			}

			unset($entry);

			if ($split) {
				$payload = ["default" => [], "custom" => []];

				foreach ($entries as $id => $entry) {
					$bucket = $entry["_bucket"];
					unset($entry["_bucket"]);
					$payload[$bucket][$id] = $entry;
				}
			} else {
				$payload = [];

				foreach ($entries as $id => $entry) {
					unset($entry["_bucket"]);
					$payload[$id] = $entry;
				}
			}

			$r = Response::ok($payload);
			$r->header("ETag", $etag);
			// no-cache (not max-age) so the browser revalidates with the ETag on
			// every request instead of serving a stale body for N seconds. The
			// revalidation short-circuits to a cheap 304 above when unchanged, so
			// this stays efficient while reflecting create/update/delete immediately.
			$r->header("Cache-Control", "private, no-cache");

			return $r;
		}

		public function get(Request $request) {
			$id = (string)$request->route_params["id"];
			$ft = BigTreeJSONDB::get("field-types", $id);

			if (!$ft) {
				throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
			}
			return Response::ok($ft);
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)$d["id"];

			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				throw new BadRequestException("id must be alphanumeric (with - or _)", "invalid_id", 400);
			}

			if (BigTreeJSONDB::exists("field-types", $id)) {
				throw new ConflictException("Field type $id already exists", "duplicate_id", 409);
			}

			$record = [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"use_cases" => $this->normalizeUseCases($d["use_cases"] ?? null),
				"self_draw" => !empty($d["self_draw"]) ? "on" : "",
			];

			BigTreeJSONDB::insert("field-types", $this->applyDeclarative($record, $d));

			return Response::created(BigTreeJSONDB::get("field-types", $id), null);
		}

		public function update(Request $request) {
			$id = (string)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("field-types", $id);

			if (!$existing) {
				throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
			}
			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"use_cases" => isset($d["use_cases"]) && is_array($d["use_cases"]) ? $this->normalizeUseCases($d["use_cases"]) : $existing["use_cases"],
				"self_draw" => isset($d["self_draw"]) ? (!empty($d["self_draw"]) ? "on" : "") : ($existing["self_draw"] ?? ""),
			]);
			BigTreeJSONDB::update("field-types", $id, $this->applyDeclarative($next, $d));

			return Response::ok(BigTreeJSONDB::get("field-types", $id));
		}

		public function delete(Request $request) {
			global $admin;

			$id = (string)$request->route_params["id"];

			if (!BigTreeJSONDB::exists("field-types", $id)) {
				throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
			}

			// Delegate to the admin method so the SPA and legacy developer UI share
			// one complete cascade (cache, source files, extension dir + manifest).
			$bigtree_admin = $admin instanceof BigTreeAdmin ? $admin : new BigTreeAdmin();
			$bigtree_admin->deleteFieldType($id);

			return Response::noContent();
		}

		/**
		 * GET /field-types/{id}/schema
		 * Returns a JSON schema describing how the SPA should render and validate
		 * this field type natively. For built-in types, schemas live in
		 * core/inc/bigtree/api/field-type-schemas.php (overridable via custom/).
		 * For unknown/custom types, returns a minimal {self_draw: true} stub so the
		 * SPA falls back to POST /field-types/{id}/render.
		 */
		public function schema(Request $request) {
			$id = (string)$request->route_params["id"];
			$schemas = $this->loadSchemas();

			if (isset($schemas[$id]) && is_array($schemas[$id])) {
				$schema = $schemas[$id];
				$schema["render"] = $this->renderKind($schema, "default");
				$schema["contract_version"] = (int)($schema["contract_version"] ?? 1);

				// Schema-file types are first-party (shipped in core/custom php), so
				// they default to a high trust level — a module among them may load
				// in-context rather than being forced into the sandbox.
				if (!isset($schema["trust"])) {
					$schema["trust"] = "core";
				}

				$r = Response::ok($schema);
				$r->header("Cache-Control", "private, max-age=300");

				return $r;
			}

			// Fall back: custom or extension field type — registered in JSONDB. A
			// declarative type carries an input_schema (composed from the primitive
			// controls); everything else gets the server-render bridge stub.
			$ft = BigTreeJSONDB::get("field-types", $id);

			if (!$ft) {
				throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
			}

			$render = $this->renderKind($ft, "custom");

			if ($render === "module") {
				$r = Response::ok([
					"id" => $id,
					"name" => $ft["name"] ?? $id,
					"category" => "custom",
					"render" => "module",
					"value_type" => isset($ft["value_type"]) ? (string)$ft["value_type"] : "string",
					"contract_version" => (int)($ft["contract_version"] ?? 1),
					"asset_url" => (string)$ft["asset_url"],
					// SRI hash pinned at install/build; the sandbox refuses an
					// unsigned (empty) module, so untrusted code can't be swapped out
					// from under a verified manifest.
					"integrity" => isset($ft["integrity"]) ? (string)$ft["integrity"] : "",
					"trust" => isset($ft["trust"]) ? (string)$ft["trust"] : "marketplace",
					"settings_schema" => is_array($ft["settings_schema"] ?? null) ? $ft["settings_schema"] : [],
				]);
				$r->header("Cache-Control", "private, max-age=60");

				return $r;
			}

			if ($render === "declarative") {
				$r = Response::ok([
					"id" => $id,
					"name" => $ft["name"] ?? $id,
					"category" => "custom",
					"render" => "declarative",
					"value_type" => isset($ft["value_type"]) ? (string)$ft["value_type"] : "object",
					"contract_version" => (int)($ft["contract_version"] ?? 1),
					"input_schema" => array_values($ft["input_schema"]),
					"settings_schema" => is_array($ft["settings_schema"] ?? null) ? $ft["settings_schema"] : [],
				]);
				$r->header("Cache-Control", "private, max-age=60");

				return $r;
			}

			$r = Response::ok([
				"id" => $id,
				"name" => $ft["name"] ?? $id,
				"category" => "custom",
				"render" => $render,
				"value_type" => "string",
				"ui" => ["component" => "ServerRendered", "props" => []],
				"settings_schema" => [],
				"self_draw" => !empty($ft["self_draw"]),
				"render_fallback" => true,
			]);
			$r->header("Cache-Control", "private, max-age=60");

			return $r;
		}

		/**
		 * POST /field-types/{id}/render
		 * Body: { field: { key, value, id, title, subtitle, tabindex, settings, required } }
		 *
		 * Includes the field type's draw.php (custom override path first, then core,
		 * then extension), captures output, returns HTML. The SPA mounts that HTML
		 * inside a sandboxed component until the type author opts in with a schema.
		 *
		 * Security:
		 *  - Authenticated callers only (route-level).
		 *  - $field["value"] passes through to draw.php which is responsible for
		 *    escaping (standard BigTree convention is BigTree::safeEncode).
		 *  - We do NOT trust the caller's "settings" verbatim for type lookup —
		 *    the field type id from the URL is the only thing that picks the file.
		 */
		public function render(Request $request) {
			global $bigtree, $admin, $cms;

			$id = (string)$request->route_params["id"];
			$path = $this->resolveDrawPath($id);

			if (!$path) {
				throw new NotFoundException("Field type $id has no draw template", "render_unavailable", 404);
			}

			$incoming = is_array($request->body["field"] ?? null) ? $request->body["field"] : [];

			$field = [
				"type" => $id,
				"key" => (string)($incoming["key"] ?? "field"),
				"value" => $incoming["value"] ?? "",
				"id" => (string)($incoming["id"] ?? ("field-" . bin2hex(random_bytes(4)))),
				"title" => (string)($incoming["title"] ?? ""),
				"subtitle" => (string)($incoming["subtitle"] ?? ""),
				"tabindex" => (int)($incoming["tabindex"] ?? 0),
				"settings" => is_array($incoming["settings"] ?? null) ? $incoming["settings"] : [],
				"required" => !empty($incoming["required"]),
				"has_value" => array_key_exists("value", $incoming),
			];

			ob_start();

			try {
				include $path;
			} catch (\Throwable $e) {
				ob_end_clean();
				throw new BadRequestException("Render failed: " . $e->getMessage(), "render_error", 400);
			}

			$html = (string)ob_get_clean();

			return Response::ok([
				"id" => $id,
				"html" => $html,
				"meta" => ["rendered_at" => date("c")],
			]);
		}

		// — helpers —

		/**
		 * Compute a Subresource-Integrity string ("sha384-<base64>") for a module
		 * bundle. Pinned into a field type's record at extension build/install time
		 * so the sandbox can verify the served bytes before executing them. Returns
		 * "" when the file is unreadable.
		 */
		public static function computeIntegrity($path, $algo = "sha384") {
			if (!is_file($path) || !is_readable($path)) {
				return "";
			}

			$raw = hash_file($algo, $path, true);

			if ($raw === false) {
				return "";
			}

			return $algo . "-" . base64_encode($raw);
		}

		/**
		 * Derive the SPA render contract for a field type from its schema entry or
		 * JSONDB record. An explicit "render" key wins; otherwise an input_schema
		 * means declarative, an asset_url means a (sandboxed) JS module, self_draw
		 * means the server-render bridge. Built-ins ("default" bucket) without any
		 * of those are first-party React components.
		 */
		private function renderKind(array $source, $bucket) {
			if (!empty($source["render"])) {
				return (string)$source["render"];
			}

			if (!empty($source["input_schema"]) && is_array($source["input_schema"])) {
				return "declarative";
			}

			if (!empty($source["asset_url"])) {
				return "module";
			}

			if (!empty($source["self_draw"])) {
				return "server";
			}

			return $bucket === "default" ? "core-component" : "server";
		}

		/**
		 * Fold the declarative-render fields (render / value_type / input_schema)
		 * from a request body into a JSONDB record. A non-empty input_schema makes
		 * the type declarative; an explicitly empty one clears it back to a
		 * server-rendered type. Keys absent from the body are left untouched so a
		 * partial PATCH never wipes an existing input_schema by omission.
		 */
		private function applyDeclarative(array $record, array $body) {
			if (array_key_exists("input_schema", $body)) {
				$schema = is_array($body["input_schema"]) ? $this->sanitizeInputSchema($body["input_schema"]) : [];

				if (!empty($schema)) {
					$record["input_schema"] = $schema;
					$record["render"] = "declarative";
					$record["value_type"] = isset($body["value_type"]) ? (string)$body["value_type"] : "object";
				} else {
					unset($record["input_schema"]);

					if (($record["render"] ?? "") === "declarative") {
						unset($record["render"]);
					}
				}
			} elseif (isset($body["render"])) {
				$record["render"] = (string)$body["render"];
			}

			return $record;
		}

		/**
		 * Normalize a use_cases value into the associative map the rest of BigTree
		 * expects — {use_case => "on"}. The SPA posts a flat list of slugs
		 * (["templates", "modules"]) while extensions and the legacy admin store the
		 * map form; getCachedFieldTypes() iterates as $case => $val, so a list ends
		 * up filed under numeric keys and the type never appears for any real use
		 * case. Accept either shape on the way in and always persist the map.
		 */
		private function normalizeUseCases($input) {
			if (!is_array($input)) {
				return [];
			}

			$normalized = [];

			foreach ($input as $key => $value) {
				// List form: ["templates", ...] — the slug is the value.
				if (is_int($key)) {
					if (is_string($value) && $value !== "") {
						$normalized[$value] = "on";
					}

					continue;
				}

				// Map form: {"templates" => "on"|true|...} — keep truthy entries.
				if (!empty($value)) {
					$normalized[$key] = "on";
				}
			}

			return $normalized;
		}

		/**
		 * Validate and whitelist a declarative input_schema. Each descriptor must
		 * carry a key-safe id and a field-type slug; only known keys survive.
		 * Throws a 400 on a malformed descriptor so the author gets a clear error
		 * rather than a silently broken field type.
		 */
		private function sanitizeInputSchema(array $input) {
			$clean = [];
			$seen = [];

			foreach ($input as $descriptor) {
				if (!is_array($descriptor)) {
					continue;
				}

				$id = isset($descriptor["id"]) ? trim((string)$descriptor["id"]) : "";
				$type = isset($descriptor["type"]) ? trim((string)$descriptor["type"]) : "";

				if ($id === "" || $type === "") {
					throw new BadRequestException("Each input field needs an id and a type", "invalid_input_schema", 400);
				}

				if (!preg_match('/^[a-z0-9_-]+$/i', $id)) {
					throw new BadRequestException("Input field id \"$id\" must be alphanumeric (with - or _)", "invalid_input_schema", 400);
				}

				if (!preg_match('/^[a-z0-9_-]+$/i', $type)) {
					throw new BadRequestException("Input field type \"$type\" is invalid", "invalid_input_schema", 400);
				}

				if (isset($seen[$id])) {
					throw new BadRequestException("Duplicate input field id \"$id\"", "invalid_input_schema", 400);
				}

				$seen[$id] = true;
				$entry = ["id" => $id, "type" => $type];

				if (isset($descriptor["title"])) {
					$entry["title"] = (string)$descriptor["title"];
				}

				if (isset($descriptor["subtitle"])) {
					$entry["subtitle"] = (string)$descriptor["subtitle"];
				}

				if (!empty($descriptor["required"])) {
					$entry["required"] = true;
				}

				if (isset($descriptor["settings"]) && is_array($descriptor["settings"])) {
					$entry["settings"] = $descriptor["settings"];
				}

				$clean[] = $entry;
			}

			return $clean;
		}

		private function loadSchemas() {
			$schemas = include SERVER_ROOT . "core/inc/bigtree/api/field-type-schemas.php";
			$custom = SERVER_ROOT . "custom/inc/bigtree/api/field-type-schemas.php";

			if (file_exists($custom)) {
				$overrides = include $custom;

				if (is_array($overrides)) {
					$schemas = array_replace($schemas, $overrides);
				}
			}

			return is_array($schemas) ? $schemas : [];
		}

		private function resolveDrawPath($id) {
			// Sanity: id must be a directory-safe name to prevent traversal.
			if (!preg_match('/^[a-z0-9_-]+$/i', $id)) {
				return null;
			}

			// 1. Custom override path
			$custom = SERVER_ROOT . "custom/admin/field-types/$id/draw.php";

			if (file_exists($custom)) {
				return $custom;
			}

			// 2. Core built-in
			$core = SERVER_ROOT . "core/admin/field-types/$id/draw.php";

			if (file_exists($core)) {
				return $core;
			}

			// 3. Registered custom/extension type via JSONDB record
			$ft = BigTreeJSONDB::get("field-types", $id);

			if ($ft && !empty($ft["extension"])) {
				$ext = SERVER_ROOT . "extensions/" . preg_replace('/[^a-z0-9._-]/i', '', $ft["extension"]) . "/field-types/$id/draw.php";

				if (file_exists($ext)) {
					return $ext;
				}
			}

			return null;
		}
	}
