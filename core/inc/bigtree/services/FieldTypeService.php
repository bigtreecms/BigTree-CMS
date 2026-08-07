<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\FieldSpec;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeJSONDB;
	use BigTree;

	/**
	 * Field types: built-in + custom registry. The SPA's most-pulled endpoint
	 * because every form-rendering call needs it. ETag'd against the JSONDB file
	 * mtime so unchanged registries are served as 304.
	 */
	class FieldTypeService {
		// Column => transform verb (see FieldSpec). use_cases (normalizeUseCases)
		// and the render-mode fields (applyRenderFields) stay as per-entity specials.
		private const FIELDS = [
			"name" => "encode",
			"self_draw" => "checkbox",
		];

		/**
		 * The reserved field-type-schemas.php key holding the settings every field
		 * type carries (see universalSettingsSchema()). Not a field type.
		 */
		public const UNIVERSAL_SCHEMA_KEY = "_universal";

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
				// no-cache so the browser revalidates with the ETag on every request
				// instead of serving a stale 304 window.
				return Response::notModified($etag)->noCache();
			}

			$split = $request->queryBool("split");

			// getCachedFieldTypes() returns data nested by use_case for the legacy
			// admin. Flatten into the SPA-friendly shape: Record<typeId, FieldType>
			// (or {default, custom} of the same) with use_cases as an array.
			$nested = FieldTypeService::getCachedFieldTypes(true);
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

			// no-cache (not max-age) so the browser revalidates with the ETag on
			// every request instead of serving a stale body for N seconds. The
			// revalidation short-circuits to a cheap 304 above when unchanged, so
			// this stays efficient while reflecting create/update/delete immediately.
			return Response::ok($payload)
				->header("ETag", $etag)
				->noCache();
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$ft = Entity::findOrFailJson("field-types", $id, "Field type");

			// Local module types keep their source + settings on disk; surface both so
			// the editor can load them (falling back to any record-stored source
			// pre-migration).
			if (($ft["render"] ?? "") === "module" && empty($ft["asset_url"])) {
				$source = $this->readModuleSource($id);
				$ft["module_source"] = $source !== "" ? $source : (string)($ft["module_source"] ?? "");
				$ft["settings_schema"] = $this->readSettingsSchema($id);
				$ft["settings_parse_error"] = $this->settingsParseFailed($id);
			}

			return Response::ok($ft);
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (new JsonStore("field-types", "Field type"))->requireNewId($d);

			if (!isset($d["name"])) {
				$d["name"] = $id;
			}

			$record = FieldSpec::insert($d, self::FIELDS);
			$record["id"] = $id;
			$record["use_cases"] = $this->normalizeUseCases($d["use_cases"] ?? null);

			BigTreeJSONDB::insert("field-types", $this->applyRenderFields($record, $d));

			return Response::created(BigTreeJSONDB::get("field-types", $id), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("field-types", $id, "Field type");
			$d = $request->body;
			$next = array_merge($existing, FieldSpec::update($d, self::FIELDS));

			if (isset($d["use_cases"]) && is_array($d["use_cases"])) {
				$next["use_cases"] = $this->normalizeUseCases($d["use_cases"]);
			}

			BigTreeJSONDB::update("field-types", $id, $this->applyRenderFields($next, $d));

			return Response::ok(BigTreeJSONDB::get("field-types", $id));
		}

		public function delete(Request $request) {
			global $admin;

			$id = $request->routeParam("id");
			(new JsonStore("field-types", "Field type"))->assertExists($id);

			// Delegate to the admin method so the SPA and legacy developer UI share
			// one complete cascade (cache, source files, extension dir + manifest).
			$bigtree_admin = LegacyAdmin::bridge();
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
			$id = $request->routeParam("id");
			$schemas = $this->loadSchemas();

			if (isset($schemas[$id]) && is_array($schemas[$id])) {
				$schema = $schemas[$id];
				$schema["render"] = $this->renderKind($schema, "default");
				$schema["contract_version"] = (int)($schema["contract_version"] ?? 1);
				// The universal settings the SPA's settings editor renders for every
				// field type; declared once rather than per type.
				$schema["settings_schema"] = self::withUniversalSettings(
					is_array($schema["settings_schema"] ?? null) ? $schema["settings_schema"] : []
				);

				// Schema-file types are first-party (shipped in core/custom php), so
				// they default to a high trust level — a module among them may load
				// in-context rather than being forced into the sandbox.
				if (!isset($schema["trust"])) {
					$schema["trust"] = "core";
				}

				return Response::ok($schema)->cacheFor(300);
			}

			// Fall back: custom or extension field type — registered in JSONDB. A
			// declarative type carries an input_schema (composed from the primitive
			// controls); everything else gets the server-render bridge stub.
			$ft = Entity::findOrFailJson("field-types", $id, "Field type");

			$render = $this->renderKind($ft, "custom");

			if ($render === "module") {
				$payload = [
					"id" => $id,
					"name" => $ft["name"] ?? $id,
					"category" => "custom",
					"render" => "module",
					"value_type" => isset($ft["value_type"]) ? (string)$ft["value_type"] : "string",
					"contract_version" => (int)($ft["contract_version"] ?? 1),
				];

				if (!empty($ft["asset_url"])) {
					// Installed-extension module: external bundle, SRI-pinned at build.
					$payload["asset_url"] = (string)$ft["asset_url"];
					$payload["integrity"] = isset($ft["integrity"]) ? (string)$ft["integrity"] : "";
					$payload["trust"] = isset($ft["trust"]) ? (string)$ft["trust"] : "marketplace";
					$payload["settings_schema"] = self::withUniversalSettings(
						is_array($ft["settings_schema"] ?? null) ? $ft["settings_schema"] : []
					);
				} else {
					// Locally authored, implicitly trusted — source + settings on disk,
					// run in-context. (Fall back to a record-stored source for records
					// saved before source moved to the filesystem.)
					$source = $this->readModuleSource($id);

					if ($source === "" && !empty($ft["module_source"])) {
						$source = (string)$ft["module_source"];
					}

					$payload["module_source"] = $source;
					$payload["trust"] = "local";
					$payload["settings_schema"] = self::withUniversalSettings($this->readSettingsSchema($id));
				}

				return Response::ok($payload)->cacheFor(60);
			}

			if ($render === "declarative") {
				return Response::ok([
					"id" => $id,
					"name" => $ft["name"] ?? $id,
					"category" => "custom",
					"render" => "declarative",
					"value_type" => isset($ft["value_type"]) ? (string)$ft["value_type"] : "object",
					"contract_version" => (int)($ft["contract_version"] ?? 1),
					"input_schema" => array_values($ft["input_schema"]),
					"settings_schema" => self::withUniversalSettings(
						is_array($ft["settings_schema"] ?? null) ? $ft["settings_schema"] : []
					),
				])->cacheFor(60);
			}

			return Response::ok([
				"id" => $id,
				"name" => $ft["name"] ?? $id,
				"category" => "custom",
				"render" => $render,
				"value_type" => "string",
				"ui" => ["component" => "ServerRendered", "props" => []],
				// render_fallback sends the SPA to the raw-JSON settings control, which
				// reads no descriptors at all — so the universal ones aren't appended
				// here; there is nothing to render them.
				"settings_schema" => [],
				"self_draw" => !empty($ft["self_draw"]),
				"render_fallback" => true,
			])->cacheFor(60);
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

			$id = $request->routeParam("id");
			$path = $this->resolveDrawPath($id);

			if (!$path) {
				throw new NotFoundException("Field type $id has no draw template", "render_unavailable");
			}

			$incoming = $request->bodyMap("field");
			$settings = is_array($incoming["settings"] ?? null) ? $incoming["settings"] : [];
			$key = (string)($incoming["key"] ?? "field");

			$field = [
				"type" => $id,
				"key" => $key,
				// Legacy draw.php files read the field key from "column" and their
				// configured settings from "options"; the SPA sends "key"/"settings",
				// so mirror both names for compatibility.
				"column" => $key,
				"value" => $incoming["value"] ?? "",
				"id" => (string)($incoming["id"] ?? ("field-" . bin2hex(random_bytes(4)))),
				"title" => (string)($incoming["title"] ?? ""),
				"subtitle" => (string)($incoming["subtitle"] ?? ""),
				"tabindex" => (int)($incoming["tabindex"] ?? 0),
				"settings" => $settings,
				"options" => $settings,
				"required" => !empty($incoming["required"]),
				"has_value" => array_key_exists("value", $incoming),
			];

			// Extension field types expect their extension context set (relative
			// resource paths, Extension::cacheData, etc.) — mirror admin.php.
			$saved_context = $bigtree["extension_context"] ?? null;

			if (strpos($id, "*") !== false) {
				[$extension] = explode("*", $id, 2);
				$bigtree["extension_context"] = $extension;
			}

			ob_start();

			try {
				include $path;
			} catch (\Throwable $e) {
				ob_end_clean();
				$bigtree["extension_context"] = $saved_context;
				throw new BadRequestException("Render failed: " . $e->getMessage(), "render_error");
			}

			$html = (string)ob_get_clean();
			$bigtree["extension_context"] = $saved_context;

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

			if (!empty($source["asset_url"]) || !empty($source["module_source"])) {
				return "module";
			}

			if (!empty($source["self_draw"])) {
				return "server";
			}

			return $bucket === "default" ? "core-component" : "server";
		}

		/**
		 * Persist the render-mode fields from a request body. A `module` type stores
		 * asset_url / trust / integrity (and drops any input_schema); everything else
		 * falls through to the declarative handler. The legacy `server` (draw.php)
		 * mode is no longer authored here — it survives only on already-installed
		 * records and is rendered via the POST /render bridge.
		 */
		private function applyRenderFields(array $record, array $body) {
			$render = isset($body["render"]) ? (string)$body["render"] : null;

			if ($render !== "module") {
				return $this->applyDeclarative($record, $body);
			}

			// Locally authored module: the source is stored on the record and served
			// to the SPA, which runs it in-context. It's implicitly trusted (you wrote
			// it on your own install) — trust / integrity only matter when a module is
			// packaged into an extension for someone else, which is the build step's
			// job, not this editor's.
			$source = isset($body["module_source"]) ? (string)$body["module_source"] : "";

			if (trim($source) === "") {
				throw new BadRequestException("A module field type needs its source code.", "missing_module_source");
			}

			$id = (string)($record["id"] ?? "");

			// Store source + settings on disk under custom/admin/field-types/{id}/ so
			// they can be edited in a real editor — not buried in field-types.json.
			// The record only carries marker fields; schema()/get() read the files.
			$this->writeModuleSource($id, $source);

			// Settings schema (built in the SPA) → settings.js. Only written when
			// supplied, so a partial update doesn't wipe it.
			if (isset($body["settings_schema"]) && is_array($body["settings_schema"])) {
				$this->writeSettingsSchema($id, $this->sanitizeSettingsSchema($body["settings_schema"]));
			}

			$record["render"] = "module";
			$record["trust"] = "local";
			$record["value_type"] = isset($body["value_type"]) ? (string)$body["value_type"] : "string";
			$record["self_draw"] = "";
			unset(
				$record["input_schema"],
				$record["asset_url"],
				$record["integrity"],
				$record["module_source"],
				$record["settings_schema"]
			);

			return $record;
		}

		/**
		 * Whitelist a module's declared settings descriptors. Each needs an id and a
		 * known control; recognised descriptor keys pass through, everything else is
		 * dropped so a module can't inject arbitrary data into the settings designer.
		 */
		private function sanitizeSettingsSchema(array $input) {
			$controls = [
				"string", "int", "textarea", "bool", "enum", "note", "heading",
				"directory", "db_table", "db_column", "db_column_sort", "list_maker",
				"source_fields", "callout_groups", "image_options", "matrix_columns",
			];
			$keys = [
				"label", "hint", "note", "heading", "placeholder", "default", "required",
				"options", "depends_on", "show_if", "contexts", "context_defaults",
				"columns", "keys",
			];
			$clean = [];

			foreach ($input as $descriptor) {
				if (!is_array($descriptor)) {
					continue;
				}

				$id = isset($descriptor["id"]) ? trim((string)$descriptor["id"]) : "";
				$control = isset($descriptor["control"]) ? (string)$descriptor["control"] : "";

				if ($id === "" || !in_array($control, $controls, true)) {
					continue;
				}

				$entry = ["id" => $id, "control" => $control];

				foreach ($keys as $key) {
					if (array_key_exists($key, $descriptor)) {
						$entry[$key] = $descriptor[$key];
					}
				}

				$clean[] = $entry;
			}

			return $clean;
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
					throw new BadRequestException("Each input field needs an id and a type", "invalid_input_schema");
				}

				if (!preg_match('/^[a-z0-9_-]+$/i', $id)) {
					throw new BadRequestException("Input field id \"$id\" must be alphanumeric (with - or _)", "invalid_input_schema");
				}

				if (!preg_match('/^[a-z0-9_-]+$/i', $type)) {
					throw new BadRequestException("Input field type \"$type\" is invalid", "invalid_input_schema");
				}

				if (isset($seen[$id])) {
					throw new BadRequestException("Duplicate input field id \"$id\"", "invalid_input_schema");
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

			return self::schemas();
		}

		/**
		 * Every field type's declared schema, keyed by type id, with any custom
		 * override applied. Cached for the request.
		 *
		 * Static because the schemas are the single source of truth for what a field
		 * type needs to be configured *completely* — the SPA validates a field against
		 * its own `settings_schema` (field-settings/validate.ts) and Resources needs
		 * the same rule server-side, so the AI can't author a field the editor will
		 * then refuse to save.
		 *
		 * @return array<string,array<string,mixed>>
		 */
		public static function schemas(): array {
			static $cache = null;

			if ($cache === null) {
				$schemas = self::rawSchemas();
				// Reserved, and not a field type: pulled out here so every consumer that
				// walks schemas() as "the list of field types" (FieldTypeDomain, the
				// /field-types/{id}/schema lookup) keeps seeing only field types.
				unset($schemas[self::UNIVERSAL_SCHEMA_KEY]);
				$cache = $schemas;
			}

			return $cache;
		}

		/** The declaration file as written, universal entry included. */
		private static function rawSchemas(): array {
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

		/**
		 * Settings every field type carries, whatever its own schema declares.
		 *
		 * Declared once in field-type-schemas.php rather than copied into each type:
		 * `default` is read by PageService::normalizePageResources and the SPA's
		 * FormRenderer for *any* field type, so a per-type copy would have to be
		 * added to two dozen schemas and to every extension type that will ever
		 * exist. Cached for the request alongside schemas().
		 *
		 * @return list<array<string,mixed>>
		 */
		public static function universalSettingsSchema(): array {
			static $cache = null;

			if ($cache === null) {
				$universal = self::rawSchemas()[self::UNIVERSAL_SCHEMA_KEY]["settings_schema"] ?? null;
				$cache = is_array($universal) ? array_values(array_filter($universal, "is_array")) : [];
			}

			return $cache;
		}

		/**
		 * One field type's `settings_schema` descriptors, the universal ones appended
		 * (just the universal ones when the type declares none, or isn't known).
		 *
		 * @return list<array<string,mixed>>
		 */
		public static function settingsSchema(string $type): array {
			$schema = self::schemas()[$type] ?? null;
			$declared = is_array($schema) && is_array($schema["settings_schema"] ?? null)
				? array_values(array_filter($schema["settings_schema"], "is_array"))
				: [];

			return self::withUniversalSettings($declared);
		}

		/**
		 * Append the universal descriptors to a declared settings schema, skipping any
		 * the schema already spells for itself — a field type that wants a different
		 * label or control for `default` keeps its own.
		 *
		 * @param list<array<string,mixed>> $declared
		 * @return list<array<string,mixed>>
		 */
		public static function withUniversalSettings(array $declared): array {
			$declared = array_values(array_filter($declared, "is_array"));
			$ids = [];

			foreach ($declared as $descriptor) {
				$ids[] = (string)($descriptor["id"] ?? "");
			}

			foreach (self::universalSettingsSchema() as $descriptor) {
				if (!in_array((string)($descriptor["id"] ?? ""), $ids, true)) {
					$declared[] = $descriptor;
				}
			}

			return $declared;
		}

		/**
		 * A single path segment is safe when it's non-empty, contains only
		 * letters / digits / dot / dash / underscore (extension ids are reverse-DNS
		 * like "com.fastspot.date-range"), and has no ".." traversal.
		 */
		private function safeSegment($segment) {

			return \BigTree\Api\Sanitize::pathSegment((string)$segment);
		}

		/** Filesystem path of a local module type's source. */
		private function moduleSourcePath($id) {
			return SERVER_ROOT . "custom/admin/field-types/$id/draw.js";
		}

		/** Write a local module type's source to disk, creating the directory. */
		private function writeModuleSource($id, $source) {
			if (!$this->safeSegment($id)) {
				throw new BadRequestException("Invalid field type id.", "invalid_id");
			}

			if (!BigTree::putFile($this->moduleSourcePath($id), $source)) {
				throw new BadRequestException("Could not write the module file — check that custom/admin/field-types/ is writable.", "module_write_failed");
			}
		}

		/** Read a local module type's source from disk ("" if none). */
		private function readModuleSource($id) {
			if (!$this->safeSegment($id)) {
				return "";
			}

			$path = $this->moduleSourcePath($id);

			return is_file($path) ? (string)file_get_contents($path) : "";
		}

		/** Filesystem path of a local module type's settings schema. */
		private function settingsSchemaPath($id) {
			return SERVER_ROOT . "custom/admin/field-types/$id/settings.js";
		}

		/**
		 * Write a module type's settings schema to settings.js as an ES module
		 * (`export default <json>;`) — valid, editable JS whose payload we can read
		 * back without a JS engine (see readSettingsSchema).
		 */
		private function writeSettingsSchema($id, array $descriptors) {
			if (!$this->safeSegment($id)) {
				throw new BadRequestException("Invalid field type id.", "invalid_id");
			}

			$json = json_encode(
				array_values($descriptors),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			$contents = "export default " . $json . ";\n";

			if (!BigTree::putFile($this->settingsSchemaPath($id), $contents)) {
				throw new BadRequestException("Could not write the settings file — check that custom/admin/field-types/ is writable.", "settings_write_failed");
			}
		}

		/**
		 * Read a module type's settings schema from settings.js. Strips the
		 * `export default … ;` wrapper we write and decodes the JSON payload; returns
		 * [] for a missing file or a hand-edit we can't parse.
		 */
		private function readSettingsSchema($id) {
			if (!$this->safeSegment($id)) {
				return [];
			}

			$path = $this->settingsSchemaPath($id);

			if (!is_file($path)) {
				return [];
			}

			$decoded = $this->looseJsonDecode($this->settingsSchemaPayload($path));

			return is_array($decoded) ? $decoded : [];
		}

		/** Strip the `export default … ;` wrapper, leaving the JSON-ish payload. */
		private function settingsSchemaPayload($path) {
			$raw = trim((string)file_get_contents($path));
			$raw = preg_replace('/^export\s+default\s+/', "", $raw);

			return rtrim($raw, "; \t\r\n");
		}

		/**
		 * True when settings.js exists with real content we couldn't parse — so the
		 * SPA can warn that the shown (empty) settings don't reflect the file, rather
		 * than silently dropping the author's hand-edit.
		 */
		private function settingsParseFailed($id) {
			if (!$this->safeSegment($id)) {
				return false;
			}

			$path = $this->settingsSchemaPath($id);

			if (!is_file($path)) {
				return false;
			}

			$payload = $this->settingsSchemaPayload($path);

			if ($payload === "" || $payload === "[]") {
				return false;
			}

			return !is_array($this->looseJsonDecode($payload));
		}

		/**
		 * Decode the payload of a settings.js file. Tries strict JSON first (the
		 * format we write), then tolerates the JS object-literal style a developer is
		 * likely to hand-write — single quotes, unquoted keys, trailing commas, and
		 * line/block comments — by normalizing to JSON in a single string-aware pass
		 * (so string contents are never rewritten).
		 */
		private function looseJsonDecode($raw) {
			$decoded = json_decode($raw, true);

			if (is_array($decoded)) {
				return $decoded;
			}

			$out = "";
			$len = strlen($raw);
			$quote = null;

			for ($i = 0; $i < $len; $i++) {
				$ch = $raw[$i];

				// Inside a string: copy through, normalising the delimiter to ".
				if ($quote !== null) {
					if ($ch === "\\") {
						$out .= $ch . ($raw[$i + 1] ?? "");
						$i++;
					} elseif ($ch === $quote) {
						$out .= '"';
						$quote = null;
					} elseif ($ch === '"') {
						$out .= '\\"';
					} else {
						$out .= $ch;
					}

					continue;
				}

				// Comments.
				if ($ch === "/" && ($raw[$i + 1] ?? "") === "/") {
					while ($i < $len && $raw[$i] !== "\n") {
						$i++;
					}

					continue;
				}

				if ($ch === "/" && ($raw[$i + 1] ?? "") === "*") {
					$i += 2;

					while ($i < $len && !($raw[$i] === "*" && ($raw[$i + 1] ?? "") === "/")) {
						$i++;
					}

					$i++;

					continue;
				}

				// String open.
				if ($ch === '"' || $ch === "'") {
					$quote = $ch;
					$out .= '"';

					continue;
				}

				// Drop a trailing comma before } or ].
				if ($ch === ",") {
					$j = $i + 1;

					while ($j < $len && ctype_space($raw[$j])) {
						$j++;
					}

					if ($j < $len && ($raw[$j] === "}" || $raw[$j] === "]")) {
						continue;
					}

					$out .= $ch;

					continue;
				}

				// A bare identifier followed by ":" is an unquoted key — quote it.
				// (true/false/null and other value identifiers pass through.)
				if (ctype_alpha($ch) || $ch === "_" || $ch === "\$") {
					$start = $i;

					while (
						$i < $len &&
						(ctype_alnum($raw[$i]) || $raw[$i] === "_" || $raw[$i] === "\$")
					) {
						$i++;
					}

					$word = substr($raw, $start, $i - $start);
					$j = $i;

					while ($j < $len && ctype_space($raw[$j])) {
						$j++;
					}

					$out .= ($j < $len && $raw[$j] === ":") ? '"' . $word . '"' : $word;
					$i--;

					continue;
				}

				$out .= $ch;
			}

			$decoded = json_decode($out, true);

			return is_array($decoded) ? $decoded : null;
		}

		private function resolveDrawPath($id) {
			// Extension-namespaced types are "{extension}*{name}" and live at
			// extensions/{extension}/field-types/{name}/draw.php — mirrors the legacy
			// resolution in admin.php. The "*" and the dots in an extension id mean
			// the whole id is NOT a single safe segment, so split first.
			if (strpos($id, "*") !== false) {
				[$extension, $local] = explode("*", $id, 2);

				if (!$this->safeSegment($extension) || !$this->safeSegment($local)) {
					return null;
				}

				$path = SERVER_ROOT . "extensions/$extension/field-types/$local/draw.php";

				return file_exists($path) ? $path : null;
			}

			if (!$this->safeSegment($id)) {
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

			// 3. Registered extension type whose record names an extension but whose
			//    id isn't "*"-namespaced (older records).
			$ft = BigTreeJSONDB::get("field-types", $id);

			if ($ft && !empty($ft["extension"]) && $this->safeSegment((string)$ft["extension"])) {
				$ext = SERVER_ROOT . "extensions/" . $ft["extension"] . "/field-types/$id/draw.php";

				if (file_exists($ext)) {
					return $ext;
				}
			}

			return null;
		}
	
		public static function getFieldType($id) {
			return BigTreeJSONDB::get("field-types", $id);
		}

		public static function getFieldTypes($sort = "name ASC") {
			$sort_pieces = explode(" ", $sort);
			$sort_column = $sort_pieces[0] ?? "";
			$sort_direction = $sort_pieces[1] ?? "";

			return BigTreeJSONDB::getAll("field-types", $sort_column, $sort_direction ?: "ASC");
		}

		/**
		 * The ids of every field type installed for a use case — the core defaults
		 * plus any custom types registered for it.
		 *
		 * $use_case is one of "modules", "templates", "callouts", "settings".
		 * Returns [] for an unknown use case, which callers should treat as
		 * "can't validate" rather than "nothing is valid".
		 *
		 * @return list<string>
		 */
		public static function availableFieldTypeIds($use_case) {
			$types = self::getCachedFieldTypes();

			if (!isset($types[$use_case]) || !is_array($types[$use_case])) {

				return [];
			}

			return array_map("strval", array_keys($types[$use_case]));
		}

		/**
		 * The field types installable for a use case as id/name pairs, sorted by id.
		 *
		 * Audit #8 B1: the AI template/callout authoring path had no way to *discover*
		 * the valid `fields[].type` ids — the field-types route family is declined for
		 * management, which conflated it with discovery, so the model guessed ids
		 * ("richtext"? "wysiwyg"?) and learned only from a recoverable error. The read
		 * seams surface this so the first proposal names a real type. Discovery only —
		 * it lists what exists, it doesn't manage anything.
		 *
		 * @param string $use_case One of "modules", "templates", "callouts", "settings".
		 * @return list<array{id:string,name:string}>
		 */
		public static function aiFieldTypeCatalog($use_case) {
			$types = self::getCachedFieldTypes();

			if (!isset($types[$use_case]) || !is_array($types[$use_case])) {

				return [];
			}

			$catalog = [];

			foreach ($types[$use_case] as $id => $definition) {
				$catalog[] = [
					"id" => (string)$id,
					"name" => (string)(is_array($definition) ? ($definition["name"] ?? $id) : $id),
				];
			}

			usort($catalog, function (array $a, array $b): int {

				return strcmp($a["id"], $b["id"]);
			});

			return $catalog;
		}

		public static function getCachedFieldTypes($split = false) {
			$types["modules"] = $types["templates"] = $types["callouts"] = $types["settings"] = [
				"default" => [
					"text" => ["name" => "Text", "self_draw" => false],
					"textarea" => ["name" => "Text Area", "self_draw" => false],
					"html" => ["name" => "HTML Area", "self_draw" => false],
					"link" => ["name" => "Link", "self_draw" => false],
					"upload" => ["name" => "File Upload", "self_draw" => false],
					"image" => ["name" => "Image Upload", "self_draw" => false],
					"video" => ["name" => "YouTube or Vimeo Video", "self_draw" => false],
					"file-reference" => ["name" => "File Reference", "self_draw" => false],
					"image-reference" => ["name" => "Image Reference", "self_draw" => false],
					"video-reference" => ["name" => "Video Reference", "self_draw" => false],
					"list" => ["name" => "List", "self_draw" => false],
					"checkbox" => ["name" => "Checkbox", "self_draw" => false],
					"date" => ["name" => "Date Picker", "self_draw" => false],
					"time" => ["name" => "Time Picker", "self_draw" => false],
					"datetime" => ["name" => "Date &amp; Time Picker", "self_draw" => false],
					"media-gallery" => ["name" => "Media Gallery", "self_draw" => false],
					"callouts" => ["name" => "Callouts", "self_draw" => false],
					"matrix" => ["name" => "Matrix", "self_draw" => false],
					"one-to-many" => ["name" => "One to Many", "self_draw" => false]
				],
				"custom" => []
			];

			$types["modules"]["default"]["route"] = ["name" => "Generated Route", "self_draw" => true];
			$field_types = BigTreeJSONDB::getAll("field-types", "name", "ASC");

			foreach ($field_types as $field_type) {
				foreach ($field_type["use_cases"] as $case => $val) {
					if ($val) {
						// `self_draw` is optional on a field-types record (a declarative or
						// module type never writes it), so read it the same way :64 does
						// rather than emitting a warning per row (audit #19 B4).
						$types[$case]["custom"][$field_type["id"]] = [
							"name" => $field_type["name"],
							"self_draw" => !empty($field_type["self_draw"]),
						];
					}
				}
			}

			// Re-merge if we don't want them split
			if (!$split) {
				foreach ($types as $use_case => $list) {
					$types[$use_case] = array_merge($list["default"], $list["custom"]);
				}
			}

			return $types;
		}

	}
