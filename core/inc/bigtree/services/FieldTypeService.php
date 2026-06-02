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

			BigTreeJSONDB::insert("field-types", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"use_cases" => is_array($d["use_cases"] ?? null) ? $d["use_cases"] : [],
				"self_draw" => !empty($d["self_draw"]) ? "on" : "",
			]);

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
				"use_cases" => isset($d["use_cases"]) && is_array($d["use_cases"]) ? $d["use_cases"] : $existing["use_cases"],
				"self_draw" => isset($d["self_draw"]) ? (!empty($d["self_draw"]) ? "on" : "") : ($existing["self_draw"] ?? ""),
			]);
			BigTreeJSONDB::update("field-types", $id, $next);

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

			if (isset($schemas[$id])) {
				$r = Response::ok($schemas[$id]);
				$r->header("Cache-Control", "private, max-age=300");

				return $r;
			}

			// Fall back: custom or extension field type — registered in JSONDB.
			$ft = BigTreeJSONDB::get("field-types", $id);

			if (!$ft) {
				throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
			}

			$r = Response::ok([
				"id" => $id,
				"name" => $ft["name"] ?? $id,
				"category" => "custom",
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
