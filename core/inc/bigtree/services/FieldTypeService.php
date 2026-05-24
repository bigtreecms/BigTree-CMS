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
			$paths = array_filter([
				SERVER_ROOT . "core/setup/json-db/field-types.json",
				file_exists(SERVER_ROOT . "custom/setup/json-db/field-types.json") ? SERVER_ROOT . "custom/setup/json-db/field-types.json" : null,
			]);
			$etag = ETag::fromMtimes($paths);

			if (ETag::check($request, $etag)) {
				$r = Response::raw(304, []); $r->is_envelope = false; $r->body = null;
				$r->header("ETag", $etag);
				return $r;
			}

			$split = !empty($request->query["split"]);
			$registry = BigTreeAdmin::getCachedFieldTypes($split);

			$r = Response::ok($registry);
			$r->header("ETag", $etag);
			$r->header("Cache-Control", "private, max-age=300");
			return $r;
		}

		public function get(Request $request) {
			$id = (string)$request->route_params["id"];
			$ft = BigTreeJSONDB::get("field-types", $id);
			if (!$ft) throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
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
			if (!$existing) throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
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
			$id = (string)$request->route_params["id"];
			if (!BigTreeJSONDB::exists("field-types", $id)) {
				throw new NotFoundException("Field type $id not found", "resource_not_found", 404);
			}
			BigTreeJSONDB::delete("field-types", $id);
			return Response::noContent();
		}
	}
