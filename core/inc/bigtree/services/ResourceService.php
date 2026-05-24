<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree;
	use BigTreeAdmin;
	use SQL;

	/**
	 * Media library: resource folders + resources + allocations + search.
	 *
	 * v1 scope: metadata CRUD + folder navigation + search + allocations.
	 * Uploads/crops are deferred — they require multipart parsing + image processing
	 * pipelines that the legacy admin handles via session-aware processField paths.
	 * The SPA can POST a created resource record after performing its own upload
	 * to a presigned URL (extension point for v2).
	 */
	class ResourceService {
		public function listFolders(Request $request) {
			$parent = (int)($request->query["parent"] ?? 0);
			$folders = SQL::fetchAll("SELECT id, parent, name FROM bigtree_resource_folders WHERE parent = ? ORDER BY name", $parent);
			$resources = SQL::fetchAll(
				"SELECT id, folder, file, name, type, mimetype, is_image, is_video, height, width, size, date FROM bigtree_resources WHERE folder = ? ORDER BY date DESC LIMIT 200",
				$parent ?: null
			);
			return Response::ok([
				"breadcrumb" => $this->folderBreadcrumb($parent),
				"folders" => array_map(function ($f) {
					return ["id" => (int)$f["id"], "parent" => (int)$f["parent"], "name" => $f["name"]];
				}, $folders),
				"resources" => array_map([$this, "presentResource"], $resources),
			]);
		}

		public function createFolder(Request $request) {
			$d = $request->body;
			$parent = (int)$d["parent"];
			$name = trim((string)$d["name"]);
			if ($name === "") throw new BadRequestException("name required", "missing_name", 400);

			$id = (int)SQL::insert("bigtree_resource_folders", [
				"parent" => $parent,
				"name" => htmlspecialchars($name),
			]);
			$row = SQL::fetch("SELECT id, parent, name FROM bigtree_resource_folders WHERE id = ?", $id);
			return Response::created(["id" => (int)$row["id"], "parent" => (int)$row["parent"], "name" => $row["name"]], null);
		}

		public function updateFolder(Request $request) {
			$id = (int)$request->route_params["id"];
			$existing = SQL::fetch("SELECT * FROM bigtree_resource_folders WHERE id = ?", $id);
			if (!$existing) throw new NotFoundException("Folder $id not found", "resource_not_found", 404);
			$update = [];
			if (isset($request->body["name"])) $update["name"] = htmlspecialchars(trim((string)$request->body["name"]));
			if (isset($request->body["parent"])) $update["parent"] = (int)$request->body["parent"];
			if ($update) SQL::update("bigtree_resource_folders", $id, $update);
			$row = SQL::fetch("SELECT id, parent, name FROM bigtree_resource_folders WHERE id = ?", $id);
			return Response::ok(["id" => (int)$row["id"], "parent" => (int)$row["parent"], "name" => $row["name"]]);
		}

		public function deleteFolder(Request $request) {
			$id = (int)$request->route_params["id"];
			if (!SQL::exists("bigtree_resource_folders", $id)) {
				throw new NotFoundException("Folder $id not found", "resource_not_found", 404);
			}
			// Children get their parent reset to 0 (orphan to root).
			SQL::query("UPDATE bigtree_resource_folders SET parent = 0 WHERE parent = ?", $id);
			SQL::query("UPDATE bigtree_resources SET folder = NULL WHERE folder = ?", $id);
			SQL::delete("bigtree_resource_folders", $id);
			return Response::noContent();
		}

		public function getResource(Request $request) {
			$id = (int)$request->route_params["id"];
			$r = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);
			if (!$r) throw new NotFoundException("Resource $id not found", "resource_not_found", 404);
			return Response::ok($this->presentResource($r, true));
		}

		public function updateResource(Request $request) {
			$id = (int)$request->route_params["id"];
			if (!SQL::exists("bigtree_resources", $id)) {
				throw new NotFoundException("Resource $id not found", "resource_not_found", 404);
			}
			$update = [];
			if (isset($request->body["name"])) $update["name"] = htmlspecialchars(trim((string)$request->body["name"]));
			if (isset($request->body["folder"])) $update["folder"] = (int)$request->body["folder"] ?: null;
			if (isset($request->body["metadata"])) $update["metadata"] = json_encode($request->body["metadata"]);
			if ($update) {
				$update["last_updated"] = "NOW()";
				SQL::update("bigtree_resources", $id, $update);
			}
			return Response::ok($this->presentResource(SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id), true));
		}

		public function deleteResource(Request $request) {
			$id = (int)$request->route_params["id"];
			if (!SQL::exists("bigtree_resources", $id)) {
				throw new NotFoundException("Resource $id not found", "resource_not_found", 404);
			}
			SQL::delete("bigtree_resources", $id);
			return Response::noContent();
		}

		public function search(Request $request) {
			$q = trim((string)($request->query["q"] ?? ""));
			if ($q === "") return Response::ok([]);
			$like = "%" . str_replace("%", "\\%", $q) . "%";
			$rows = SQL::fetchAll(
				"SELECT id, folder, file, name, type, mimetype, is_image, is_video, height, width, size, date FROM bigtree_resources WHERE name LIKE ? OR file LIKE ? ORDER BY date DESC LIMIT 50",
				$like, $like
			);
			return Response::ok(array_map([$this, "presentResource"], $rows));
		}

		public function allocations(Request $request) {
			$id = (int)$request->route_params["id"];
			$rows = SQL::fetchAll(
				"SELECT `table`, entry, updated_at FROM bigtree_resource_allocation WHERE resource = ? ORDER BY updated_at DESC",
				$id
			);
			return Response::ok(array_map(function ($a) {
				return ["table" => $a["table"], "entry" => $a["entry"], "updated_at" => $a["updated_at"]];
			}, $rows));
		}

		// — helpers —

		private function folderBreadcrumb($folder_id) {
			$out = [];
			$current = (int)$folder_id;
			while ($current > 0) {
				$row = SQL::fetch("SELECT id, parent, name FROM bigtree_resource_folders WHERE id = ?", $current);
				if (!$row) break;
				array_unshift($out, ["id" => (int)$row["id"], "name" => $row["name"]]);
				$current = (int)$row["parent"];
			}
			return $out;
		}

		private function presentResource(array $r, $detailed = false) {
			$base = [
				"id" => (int)$r["id"],
				"folder" => $r["folder"] !== null ? (int)$r["folder"] : 0,
				"file" => $r["file"],
				"name" => $r["name"],
				"type" => $r["type"],
				"mimetype" => $r["mimetype"],
				"is_image" => $r["is_image"] === "on",
				"is_video" => $r["is_video"] === "on",
				"height" => $r["height"] !== null ? (int)$r["height"] : null,
				"width" => $r["width"] !== null ? (int)$r["width"] : null,
				"size" => $r["size"] !== null ? (int)$r["size"] : null,
				"date" => $r["date"],
			];
			if ($detailed) {
				$base["location"] = $r["location"] ?? "";
				$base["md5"] = $r["md5"] ?? "";
				$base["metadata"] = json_decode($r["metadata"] ?? "{}", true) ?: new \stdClass();
				$base["crops"] = json_decode($r["crops"] ?? "[]", true) ?: [];
				$base["thumbs"] = json_decode($r["thumbs"] ?? "[]", true) ?: [];
				$base["video_data"] = json_decode($r["video_data"] ?? "{}", true) ?: new \stdClass();
			}
			return $base;
		}
	}
