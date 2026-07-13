<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Json;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Hooks;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Upload;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree;
	use BigTreeAutoModule;
	use BigTreeCMS;
	use BigTreeImage;
	use BigTreeJSONDB;
	use BigTreeStorage;
	use SQL;

	/**
	 * Media library: folders + resources + uploads + crops + allocations.
	 *
	 * Folder permission ranks: "p" publisher (create/delete), "e" editor (use/move),
	 * "n" no access. Enforced by PermissionService::userFolderLevel.
	 *
	 * Uploads accept multipart/form-data with a single "file" field plus optional
	 * "folder" (id), "name" (display name), and "settings" (JSON string with crop/preset).
	 *
	 * Allocations track which content row currently uses which resource (so we can
	 * cleanly find orphans and tell editors what would break if they delete a resource).
	 */
	class ResourceService {
		// — Folders —

		public function listFolders(Request $request) {
			$parent = $request->queryInt("parent");
			$this->enforceFolder($request->user, $parent, "e", "view");

			$folders = SQL::fetchAll("SELECT id, parent, name, EXISTS (SELECT 1 FROM bigtree_resource_folders c WHERE c.parent = bigtree_resource_folders.id) AS has_children FROM bigtree_resource_folders WHERE parent = ? ORDER BY name", $parent);
			$resources = SQL::fetchAll(
				"SELECT id, folder, file, name, type, mimetype, is_image, is_video, height, width, size, date
				 FROM bigtree_resources WHERE folder = ? ORDER BY date DESC LIMIT 200",
				$parent ?: null
			);

			// Permission-filter the folder list.
			$me = $request->user;
			$folders = array_values(array_filter($folders, function ($f) use ($me) {

				return PermissionService::userFolderLevel($me, (int)$f["id"]) !== "n";
			}));

			return Response::ok([
				"breadcrumb" => $this->folderBreadcrumb($parent),
				"folders" => array_map(function ($f) use ($me) {
					return [
						"id" => (int)$f["id"],
						"parent" => (int)$f["parent"],
						"name" => $f["name"],
						"has_children" => (bool)$f["has_children"],
						"access" => PermissionService::userFolderLevel($me, (int)$f["id"]),
					];
				}, $folders),
				"resources" => array_map([$this, "presentResource"], $resources),
				"access" => PermissionService::userFolderLevel($request->user, $parent),
			]);
		}

		/**
		 * GET /resource-folders/flat
		 * The full folder tree flattened in display order (depth-first, siblings
		 * by name) for folder-move selects. Folders the user can't access are
		 * omitted; their accessible descendants still appear (a child can carry
		 * an explicit grant even when its parent is denied), at their natural
		 * depth so indentation stays meaningful.
		 */
		public function listFoldersFlat(Request $request) {
			$rows = SQL::fetchAll("SELECT id, parent, name FROM bigtree_resource_folders ORDER BY name");
			$children = [];

			foreach ($rows as $row) {
				$children[(int)$row["parent"]][] = $row;
			}

			$me = $request->user;
			$out = [];
			$walk = function ($parent, $depth) use (&$walk, &$out, $children, $me) {
				foreach ($children[$parent] ?? [] as $row) {
					$access = PermissionService::userFolderLevel($me, (int)$row["id"]);

					if ($access !== "n") {
						$out[] = [
							"id" => (int)$row["id"],
							"parent" => (int)$row["parent"],
							"name" => $row["name"],
							"depth" => $depth,
							"access" => $access,
						];
					}

					$walk((int)$row["id"], $depth + 1);
				}
			};
			$walk(0, 0);

			return Response::ok($out);
		}

		public function createFolder(Request $request) {
			$d = $request->body;
			$parent = (int)$d["parent"];
			$this->enforceFolder($request->user, $parent, "p", "create folder inside");

			$name = trim((string)$d["name"]);

			if ($name === "") {
				throw new BadRequestException("name required", "missing_name");
			}

			$id = (int)SQL::insert("bigtree_resource_folders", [
				"parent" => $parent,
				"name" => BigTree::safeEncode($name),
			]);
			$row = SQL::fetch("SELECT id, parent, name FROM bigtree_resource_folders WHERE id = ?", $id);

			return Response::created([
				"id" => (int)$row["id"],
				"parent" => (int)$row["parent"],
				"name" => $row["name"],
				"access" => "p",
			], null);
		}

		public function updateFolder(Request $request) {
			$id = $request->id();
			$existing = Entity::findOrFail("bigtree_resource_folders", $id, "Folder");
			$this->enforceFolder($request->user, $id, "p", "modify");

			$update = [];

			if (isset($request->body["name"])) {
				$update["name"] = BigTree::safeEncode(trim((string)$request->body["name"]));
			}

			if (isset($request->body["parent"])) {
				$new_parent = (int)$request->body["parent"];
				$this->enforceFolder($request->user, $new_parent, "p", "move folder into");

				if ($this->isAncestor($id, $new_parent)) {
					throw new BadRequestException("Cannot move a folder into its own descendant", "cyclic_move");
				}

				$update["parent"] = $new_parent;
			}

			if ($update) {
				SQL::update("bigtree_resource_folders", $id, $update);
			}

			$row = SQL::fetch("SELECT id, parent, name FROM bigtree_resource_folders WHERE id = ?", $id);

			return Response::ok([
				"id" => (int)$row["id"],
				"parent" => (int)$row["parent"],
				"name" => $row["name"],
			]);
		}

		public function deleteFolder(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_resource_folders", $id, "Folder");

			$this->enforceFolder($request->user, $id, "p", "delete");

			// Children get their parent reset to the deleted folder's parent (lift rather than orphan).
			$parent = (int)SQL::fetchSingle("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $id);
			SQL::query("UPDATE bigtree_resource_folders SET parent = ? WHERE parent = ?", $parent, $id);
			SQL::query("UPDATE bigtree_resources SET folder = ? WHERE folder = ?", $parent ?: null, $id);
			SQL::delete("bigtree_resource_folders", $id);

			return Response::noContent();
		}

		// — Resources —

		public function getResource(Request $request) {
			$id = $request->id();
			$r = Entity::findOrFail("bigtree_resources", $id, "Resource");
			$this->enforceFolder($request->user, (int)$r["folder"], "e", "view resource in folder");

			return Response::ok($this->presentResource($r, true));
		}

		public function updateResource(Request $request) {
			$id = $request->id();
			$existing = Entity::findOrFail("bigtree_resources", $id, "Resource");
			$this->enforceFolder($request->user, (int)$existing["folder"], "e", "edit resource in folder");

			$update = [];

			if (isset($request->body["name"])) {
				$update["name"] = BigTree::safeEncode(trim((string)$request->body["name"]));
			}

			if (isset($request->body["metadata"])) {
				$update["metadata"] = json_encode($request->body["metadata"]);
			}

			if (array_key_exists("folder", $request->body)) {
				$new_folder = (int)$request->body["folder"];

				if ($new_folder !== (int)$existing["folder"]) {
					$this->enforceFolder($request->user, $new_folder, "e", "move resource into");
				}

				$update["folder"] = $new_folder ?: null;
			}

			if ($update) {
				$update["last_updated"] = "NOW()";
				SQL::update("bigtree_resources", $id, $update);
			}

			return Response::ok($this->presentResource(SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id), true));
		}

		/**
		 * POST /resources/{id}/replace (multipart, "file" field)
		 * Swap the stored bytes while keeping the resource row, its URL, and its
		 * allocations. Port of the legacy files/update/file.php replace branch:
		 *
		 *  - The new file is stored under the existing basename so references
		 *    keep resolving.
		 *  - Images must be at least as large as the largest existing crop (the
		 *    legacy edit form's min-size rule), get re-run through the default
		 *    media preset, and have their crops/thumbs regenerated in place.
		 *  - Managed videos have no replaceable file and are rejected.
		 */
		public function replaceResource(Request $request) {
			$id = $request->id();
			$existing = Entity::findOrFail("bigtree_resources", $id, "Resource");
			$this->enforceFolder($request->user, (int)$existing["folder"], "p", "replace resource in folder");

			if (!empty($existing["is_video"])) {
				throw new BadRequestException("Managed videos have no replaceable file", "not_replaceable");
			}

			$file = Upload::requireSingle($request);

			$mime = $this->detectMime($file["tmp_name"], $file["type"], $file["name"]);
			$file_name = pathinfo($existing["file"], PATHINFO_BASENAME);
			$storage = new BigTreeStorage();

			if (!empty($existing["is_image"])) {
				if (strpos($mime, "image/") !== 0) {
					throw new BadRequestException("The replacement for an image must be an image", "type_mismatch");
				}

				// Legacy min-size rule: the replacement must cover the largest
				// existing crop so regenerated crops don't upscale.
				$existing_crops = Json::decode($existing["crops"]);
				$min_width = $min_height = 0;

				foreach ($existing_crops as $crop) {
					$min_width = max($min_width, (int)($crop["width"] ?? 0));
					$min_height = max($min_height, (int)($crop["height"] ?? 0));
				}

				[$new_width, $new_height] = @getimagesize($file["tmp_name"]) ?: [0, 0];

				if ($new_width < $min_width || $new_height < $min_height) {
					throw new BadRequestException("Replacing this file requires a minimum image size of {$min_width}x{$min_height}", "image_too_small");
				}

				$image = new BigTreeImage($file["tmp_name"], $this->buildImageUploadSettings([]));

				if ($image->Error) {
					throw new BadRequestException("Image processing failed: " . $image->Error, "image_invalid");
				}

				// Store over the existing path; thumbs/crops then regenerate
				// against the same StoredName, overwriting their predecessors.
				$force_local = ($existing["location"] ?? "") === "local";

				if (!$image->replace($file_name, $force_local)) {
					throw new BadRequestException("Storage refused image: " . ($image->Error ?: "unknown"), "storage_failed");
				}

				[$crop_prefixes, $thumb_prefixes] = $this->generateDerivatives($image);

				// Drop derived files whose prefix didn't regenerate (preset changed
				// since the original upload) so stale crops don't linger in storage.
				$existing_thumbs = Json::decode($existing["thumbs"]);
				$stale = array_diff(
					array_merge(array_keys($existing_crops), array_keys($existing_thumbs)),
					array_merge(array_keys($crop_prefixes), array_keys($thumb_prefixes))
				);

				foreach ($stale as $prefix) {
					try {
						$storage->delete(BigTree::prefixFile($existing["file"], $prefix));
					} catch (\Throwable $e) {
						// Missing derivative isn't fatal.
					}
				}

				SQL::update("bigtree_resources", $id, [
					"mimetype" => $mime,
					"md5" => @md5_file($file["tmp_name"]),
					"size" => (int)$file["size"],
					"width" => $image->Width,
					"height" => $image->Height,
					"crops" => json_encode($crop_prefixes),
					"thumbs" => json_encode($thumb_prefixes),
					"file_last_updated" => "NOW()",
					"last_updated" => "NOW()",
				]);
			} else {
				// Non-image resource: bytes are stored verbatim under the existing
				// file name. If that name is a .svg (e.g. a legacy-uploaded SVG with
				// is_image=""), neutralize active content first — a stored SVG is
				// served inline from the site origin and is an XSS vector.
				self::sanitizeSvgUpload($file["tmp_name"], $file_name, $mime);

				if (!$storage->replace($file["tmp_name"], $file_name, "files/resources/")) {
					throw new BadRequestException("Storage refused upload", "storage_failed");
				}

				SQL::update("bigtree_resources", $id, [
					"mimetype" => $mime,
					"md5" => @md5_file($file["tmp_name"]),
					"size" => (int)$file["size"],
					"file_last_updated" => "NOW()",
					"last_updated" => "NOW()",
				]);
			}

			$fresh = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);
			Hooks::fire("resource.replaced", $fresh);

			return Response::ok($this->presentResource($fresh, true));
		}

		public function deleteResource(Request $request) {
			$id = $request->id();
			$existing = Entity::findOrFail("bigtree_resources", $id, "Resource");
			$this->enforceFolder($request->user, (int)$existing["folder"], "p", "delete resource in folder");

			// Best-effort delete of the stored bytes; allocations and the row itself follow.
			if (!empty($existing["file"])) {
				try {
					$storage = new BigTreeStorage();
					$storage->delete($existing["file"]);
				} catch (\Throwable $e) {
					// File missing from storage isn't fatal — proceed with row delete.
				}
			}

			SQL::query("DELETE FROM bigtree_resource_allocation WHERE resource = ?", $id);
			SQL::delete("bigtree_resources", $id);
			Hooks::fire("resource.deleted", $existing);

			return Response::noContent();
		}

		public function search(Request $request) {
			$q = $request->queryString("q");

			if ($q === "") {
				return Response::ok([]);
			}
			$like = Sanitize::likeTerm($q);
			$rows = SQL::fetchAll(
				"SELECT id, folder, file, name, type, mimetype, is_image, is_video, height, width, size, date
				 FROM bigtree_resources WHERE name LIKE ? OR file LIKE ? ORDER BY date DESC LIMIT 50",
				$like, $like
			);
			// Filter to folders the user can at least view.
			$me = $request->user;
			$rows = array_values(array_filter($rows, function ($r) use ($me) {

				return PermissionService::userFolderLevel($me, (int)$r["folder"]) !== "n";
			}));

			return Response::ok(array_map([$this, "presentResource"], $rows));
		}

		// — Upload (multipart) —

		public function upload(Request $request) {
			$folder = $request->bodyInt("folder");
			$this->enforceFolder($request->user, $folder, "p", "upload to");

			$file = Upload::requireSingle($request); // single-file upload semantics for v1

			$display_name = $request->bodyString("name", $file["name"]);
			$settings = Json::decode($request->body["settings"] ?? null);

			$mime = $this->detectMime($file["tmp_name"], $file["type"], $file["name"]);
			$is_image = strpos($mime, "image/") === 0;
			$is_video = strpos($mime, "video/") === 0;

			$storage = new BigTreeStorage();
			$location_type = $storage->Cloud ? "cloud" : "local";

			// Image branch: always run BigTreeImage so we generate the list-preview
			// thumb the file manager expects, plus any thumbs/crops/center_crops
			// from the default media preset. This mirrors
			// core/admin/ajax/files/dropzone-upload-image.php.
			if ($is_image) {
				$image_settings = $this->buildImageUploadSettings($settings);
				$image = new BigTreeImage($file["tmp_name"], $image_settings);

				if ($image->Error) {
					throw new BadRequestException("Image processing failed: " . $image->Error, "image_invalid");
				}

				$stored_path = $image->store($file["name"]);

				if (!$stored_path) {
					throw new BadRequestException("Storage refused image: " . ($image->Error ?: "unknown"), "storage_failed");
				}

				[$crop_prefixes, $thumb_prefixes] = $this->generateDerivatives($image);

				$id = $this->insertResource([
					"folder" => $folder ?: null,
					"file" => $stored_path,
					"name" => $display_name,
					"type" => pathinfo($file["name"], PATHINFO_EXTENSION),
					"mimetype" => $mime,
					"is_image" => "on",
					"is_video" => "",
					"md5" => @md5_file($file["tmp_name"]),
					"size" => (int)$file["size"],
					"width" => $image->Width,
					"height" => $image->Height,
					"crops" => $crop_prefixes,
					"thumbs" => $thumb_prefixes,
					"location" => $location_type,
					"video_data" => [],
					"metadata" => $settings["metadata"] ?? [],
				]);

				$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);
				Hooks::fire("resource.uploaded", $resource);

				return Response::created($this->presentResource($resource, true), null);
			}

			// Generic file branch: straight passthrough to BigTreeStorage::store.
			// SVG can't reach here today (an image/svg+xml mime is is_image and the
			// image branch's BigTreeImage gate rejects it), but if SVG is ever stored
			// through this branch its active content must be stripped first — so the
			// guard is wired in now and activates automatically the moment it is.
			self::sanitizeSvgUpload($file["tmp_name"], $file["name"], $mime);
			$stored_path = $storage->store($file["tmp_name"], $file["name"], "files/resources/");

			if (!$stored_path) {
				throw new BadRequestException("Storage refused upload", "storage_failed");
			}

			[$width, $height] = $is_image ? (@getimagesize($file["tmp_name"]) ?: [null, null]) : [null, null];
			$id = $this->insertResource([
				"folder" => $folder ?: null,
				"file" => $stored_path,
				"name" => $display_name,
				"type" => pathinfo($file["name"], PATHINFO_EXTENSION),
				"mimetype" => $mime,
				"is_image" => Flag::checkbox($is_image),
				"is_video" => Flag::checkbox($is_video),
				"md5" => @md5_file($file["tmp_name"]),
				"size" => (int)$file["size"],
				"width" => $width,
				"height" => $height,
				"crops" => [],
				"thumbs" => [],
				"location" => $location_type,
				"video_data" => [],
				"metadata" => $settings["metadata"] ?? [],
			]);

			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);
			Hooks::fire("resource.uploaded", $resource);

			return Response::created($this->presentResource($resource, true), null);
		}

		// — Managed videos —
		// Port of core/admin/modules/files/process/video.php — paste a YouTube
		// or Vimeo URL, pull oembed metadata, store the thumbnail as a local
		// resource asset, insert a is_video=on resource row.

		public function createManagedVideo(Request $request) {
			$url = $request->bodyString("url");
			$folder = $request->bodyInt("folder");
			$this->enforceFolder($request->user, $folder, "p", "create video in");

			if ($url === "") {
				throw new BadRequestException("url required", "missing_url");
			}

			$video = $this->extractVideoMetadata($url);

			// Pull the thumbnail down so we have a local preview asset.
			if (empty($video["image"])) {
				throw new BadRequestException("Could not retrieve a thumbnail for that video", "video_thumbnail_unavailable");
			}

			$extension = strtolower(pathinfo(parse_url($video["image"], PHP_URL_PATH) ?: "", PATHINFO_EXTENSION)) ?: "jpg";
			$tmp_dir = SITE_ROOT . "files/temporary/" . $request->user->id . "/";

			if (!is_dir($tmp_dir)) {
				@mkdir($tmp_dir, 0777, true);
			}
			$tmp_path = $tmp_dir . "video-" . $video["id"] . "-" . uniqid() . "." . $extension;

			if (!BigTree::copyFile($video["image"], $tmp_path) || !file_exists($tmp_path)) {
				throw new BadRequestException("Could not download the video thumbnail", "video_thumbnail_download_failed");
			}

			[$thumb_width, $thumb_height] = @getimagesize($tmp_path) ?: [null, null];

			$storage = new BigTreeStorage();
			$stored_thumb = $storage->store($tmp_path, basename($tmp_path), "files/resources/");
			@unlink($tmp_path);

			if (!$stored_thumb) {
				throw new BadRequestException("Storage refused video thumbnail", "storage_failed");
			}

			// Update the metadata blob with the locally stored image URL so the
			// admin always renders from our own storage, not the third party CDN.
			$video["image"] = $stored_thumb;

			$id = $this->insertResource([
				"folder" => $folder ?: null,
				"file" => $video["url"],
				"name" => $video["title"] ?: $video["url"],
				"type" => "video",
				"mimetype" => null,
				"is_image" => "",
				"is_video" => "on",
				"md5" => null,
				"size" => null,
				"width" => $video["width"] ?: $thumb_width,
				"height" => $video["height"] ?: $thumb_height,
				"crops" => [],
				"thumbs" => [],
				"location" => $video["service"],
				"video_data" => $video,
				"metadata" => [],
			]);

			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);

			return Response::created($this->presentResource($resource, true), null);
		}

		// — Crops —
		// Generate a new crop from an existing image resource. Returns the crop file URL.

		public function crop(Request $request) {
			$id = $request->id();
			$existing = Entity::findOrFail("bigtree_resources", $id, "Resource");
			$this->enforceFolder($request->user, (int)$existing["folder"], "e", "crop resource in folder");

			if ($existing["is_image"] !== "on") {
				throw new BadRequestException("Resource is not an image", "not_an_image");
			}

			$x = $request->bodyInt("x");
			$y = $request->bodyInt("y");
			$w = $request->bodyInt("width");
			$h = $request->bodyInt("height");
			$target_w = $request->bodyInt("target_width", $w);
			$target_h = $request->bodyInt("target_height", $h);
			$name_prefix = $request->bodyString("prefix", "crop-") ?: "crop-";
			$directory = self::safeStorageDirectory($request->bodyString("directory"), "files/resources/crops/");

			if ($w <= 0 || $h <= 0 || $target_w <= 0 || $target_h <= 0) {
				throw new BadRequestException("width/height/target_width/target_height must be > 0", "bad_dimensions");
			}

			$image = new BigTreeImage($existing["file"]);

			if ($image->Error) {
				throw new BadRequestException("Image processing failed: " . $image->Error, "image_invalid");
			}

			$temp = $image->getTempFileName();
			$image->crop($temp, $x, $y, $target_w, $target_h, $w, $h);

			$storage = new BigTreeStorage();
			$crop_name = $name_prefix . basename($existing["file"]);
			$stored_path = $storage->replace($temp, $crop_name, $directory);

			if (!$stored_path) {
				throw new BadRequestException("Storage refused crop", "storage_failed");
			}

			// crops is stored as a prefix → { width, height, ... } map to match the
			// legacy admin's shape, so user-added crops key by prefix too. Re-cropping
			// with the same prefix replaces the previous entry — that's intentional;
			// it mirrors how the storage layer would overwrite the file anyway.
			$crops = Json::decode($existing["crops"]);
			$crops[$name_prefix] = [
				"name" => $crop_name,
				"prefix" => $name_prefix,
				"directory" => $directory,
				"width" => $target_w,
				"height" => $target_h,
				"file" => $stored_path,
				"created_at" => date("Y-m-d H:i:s"),
			];
			SQL::update("bigtree_resources", $id, ["crops" => json_encode($crops), "last_updated" => "NOW()"]);

			return Response::created([
				"file" => $stored_path,
				"width" => $target_w,
				"height" => $target_h,
				"prefix" => $name_prefix,
			], null);
		}

		// — Allocations —

		public function allocations(Request $request) {
			$id = $request->id();
			$rows = SQL::fetchAll(
				"SELECT `table`, entry, updated_at FROM bigtree_resource_allocation WHERE resource = ? ORDER BY updated_at DESC",
				$id
			);

			return Response::ok(array_map(function ($a) {

				return ["table" => $a["table"], "entry" => $a["entry"], "updated_at" => $a["updated_at"]];
			}, $rows));
		}

		/**
		 * Enriched "where is this file used" list for the SPA file detail panel. Each
		 * allocation row is resolved into a human location + title + status, plus an
		 * SPA link descriptor (no legacy ADMIN_ROOT URLs). Parallels the legacy admin's
		 * ResourceAllocationService::getResourceAllocationUsage(), which emits ADMIN_ROOT links for
		 * the PHP file edit screen.
		 */
		public function usage(Request $request) {
			global $cms;

			$id = $request->id();
			Entity::assertExists("bigtree_resources", $id, "Resource");

			$allocations = ResourceAllocationService::getResourceAllocation($id);
			$usages = [];
			$module_cache = [];

			// — Pass 1: bucket allocations by destination kind so each group can be
			// resolved with a single query instead of one fetch per allocation. —
			//
			// IMPORTANT: if a new $table branch is added in the rebuild pass below, it
			// must also be bucketed here (and vice versa) — keep the two passes aligned.
			$live_page_ids = [];          // numeric bigtree_pages ids
			$module_ids = [];             // [$table => [live entry ids]]

			foreach ($allocations as $allocation) {
				$table = $allocation["table"];
				$entry = (string)$allocation["entry"];
				$pending = (substr($entry, 0, 1) === "p");

				if ($table === "bigtree_pages") {
					// Pending pages are resolved per-row (see rebuild pass) because
					// their title/status come from the pending_changes blob.
					if (!$pending) {
						$live_page_ids[$entry] = true;
					}
				} elseif ($table === "bigtree_settings") {
					// Settings live in the JSON DB (already a single cached read),
					// resolved directly in the rebuild pass — no SQL to batch.
					continue;
				} else {
					// Pending module entries are resolved per-row (getItem applies the
					// pending overlay); live entries are batched per table.
					if (!$pending) {
						$module_ids[$table][$entry] = true;
					}
				}
			}

			// — Batch-fetch each bucket into id → row maps. —
			$page_map = $this->batchPages(array_keys($live_page_ids));
			$module_maps = [];

			foreach ($module_ids as $table => $ids) {
				$module_maps[$table] = $this->batchModuleItems($table, array_keys($ids));
			}

			// — Pass 2: rebuild $usages from the maps, preserving the exact per-entry
			// fields, statuses, link descriptors, and order of the per-row version.
			// Ids missing from a batched map fall through to the "Deleted …"/status:none
			// branch exactly as the per-row if ($page)/if ($item) checks did. —
			foreach ($allocations as $allocation) {
				$table = $allocation["table"];
				$entry = (string)$allocation["entry"];
				$pending = (substr($entry, 0, 1) === "p");
				$usage = [
					"location" => $table,
					"title" => $entry,
					"status" => $pending ? "pending" : "published",
					"updated_at" => $allocation["updated_at"],
					"link" => null,
				];

				if ($table === "bigtree_pages") {
					$usage["location"] = "Pages";
					// Pending pages still go per-row; live pages come from the batch map.
					$page = $pending ? $cms->getPendingPage($entry, false) : ($page_map[$entry] ?? false);

					if ($page) {
						$usage["title"] = $page["nav_title"] ?: $page["title"];

						if (!$pending && (!empty($page["archived"]) || !empty($page["archived_inherited"]))) {
							$usage["status"] = "archived";
						}

						$usage["link"] = ["kind" => "page", "entry" => $entry];
					} else {
						$usage["title"] = "Deleted Page (".$entry.")";
						$usage["status"] = "none";
					}
				} elseif ($table === "bigtree_settings") {
					$usage["location"] = "Settings";
					$setting = SettingService::readSetting($entry);
					// Settings have no pending/archived lifecycle.
					$usage["status"] = "published";

					if ($setting) {
						$usage["title"] = $setting["name"] ?: $setting["id"];

						if (empty($setting["system"])) {
							$usage["link"] = ["kind" => "setting", "entry" => $entry];
						}
					} else {
						$usage["title"] = "Deleted Setting (".$entry.")";
						$usage["status"] = "none";
					}
				} else {
					if (!isset($module_cache[$table])) {
						$module_cache[$table] = $this->resolveModuleForTable($table);
					}

					$module_info = $module_cache[$table];
					$usage["location"] = $module_info["name"];
					// Pending module entries still go per-row; live entries come from the batch map.
					$item = $pending ? BigTreeAutoModule::getItem($table, $entry) : ($module_maps[$table][$entry] ?? false);

					if ($item) {
						$usage["title"] = $this->moduleEntryTitle($item["item"] ?? $item, $entry);

						if ($module_info["id"] && $module_info["route"]) {
							$usage["link"] = [
								"kind" => "module_entry",
								"module" => $module_info["id"],
								"route" => $module_info["route"],
								"edit_route" => $module_info["edit_route"],
								"entry" => $entry,
							];
						}
					} else {
						$usage["title"] = "Deleted Entry (".$entry.")";
						$usage["status"] = "none";
					}
				}

				$usages[] = $usage;
			}

			return Response::ok($usages);
		}

		/**
		 * Batch-fetch live page rows for usage() with a single IN(...) query, keyed by
		 * id (as a string, matching the allocation entry). Returns only the columns
		 * usage() consumes; mirrors the relevant output of $cms->getPage($entry, false)
		 * (decode = false, so no resources/open_graph/external transforms — none of
		 * which usage() reads).
		 *
		 * @param string[] $ids Live page ids.
		 * @return array<string,array> id => page row.
		 */
		private function batchPages(array $ids): array {
			if (!$ids) {

				return [];
			}

			$placeholders = \BigTree\Api\Sanitize::placeholders($ids);
			$rows = SQL::fetchAll(
				"SELECT id, nav_title, title, archived, archived_inherited FROM bigtree_pages WHERE id IN ($placeholders)",
				...$ids
			);
			$map = [];

			foreach ($rows as $row) {
				$map[(string)$row["id"]] = $row;
			}

			return $map;
		}

		/**
		 * Batch-fetch live module-entry rows for one table with a single IN(...) query,
		 * keyed by id (as a string). Each row is wrapped as ["item" => $row] to match
		 * the shape BigTreeAutoModule::getItem returns, and each field gets the same
		 * internal-page-link / untranslateArray transform getItem applies, so
		 * moduleEntryTitle() sees byte-identical values. Tags (which usage() never
		 * reads) are intentionally not fetched.
		 *
		 * @param string   $table Module table name.
		 * @param string[] $ids   Live entry ids.
		 * @return array<string,array> id => ["item" => transformed row].
		 */
		private function batchModuleItems(string $table, array $ids): array {
			if (!$ids) {

				return [];
			}

			$placeholders = \BigTree\Api\Sanitize::placeholders($ids);
			$rows = SQL::fetchAll(
				"SELECT * FROM `".str_replace("`", "", $table)."` WHERE id IN ($placeholders)",
				...$ids
			);
			$map = [];

			foreach ($rows as $row) {
				// Mirror BigTreeAutoModule::getItem's per-field decoding so titles match.
				foreach ($row as $key => $val) {
					if (is_null($val)) {
						$row[$key] = null;
					} elseif (is_array(json_decode($val, true))) {
						$row[$key] = BigTree::untranslateArray(json_decode($val, true));
					} else {
						$row[$key] = BigTreeCMS::replaceInternalPageLinks($val);
					}
				}

				$map[(string)$row["id"]] = ["item" => $row];
			}

			return $map;
		}

		/**
		 * Resolve a module's id, display name, URL route, and the edit-action route for
		 * one of its tables (cached per usage() call). The SPA builds an entry-precise
		 * link from `/modules/{route}/{edit_route}/{entry}`.
		 */
		private function resolveModuleForTable(string $table): array {
			$view = BigTreeAutoModule::getViewForTable($table);
			$module_id = $view ? BigTreeAutoModule::getModuleForView($view["id"]) : null;
			$module = $module_id ? BigTreeJSONDB::get("modules", $module_id) : null;

			if (!$module) {
				return ["id" => null, "name" => $table, "route" => null, "edit_route" => null];
			}

			$edit_route = null;

			foreach ($module["forms"] ?? [] as $form) {
				if (($form["table"] ?? "") !== $table) {
					continue;
				}

				$action = ModuleFormService::getModuleActionForForm($form);

				if ($action) {
					$edit_route = $action["route"] ?? null;
					break;
				}
			}

			return [
				"id" => $module_id,
				"name" => $module["name"] ?: $table,
				"route" => $module["route"] ?? null,
				"edit_route" => $edit_route,
			];
		}

		/** Best-effort human title for a module entry row (mirrors the legacy admin). */
		private function moduleEntryTitle($item, $entry): string {
			if (!is_array($item)) {
				return "Entry ".$entry;
			}

			foreach (["title", "name", "nav_title", "headline", "label"] as $key) {
				if (!empty($item[$key]) && is_string($item[$key])) {
					return strip_tags($item[$key]);
				}
			}

			if (!empty($item["id"])) {
				return "Entry ".$item["id"];
			}

			return "Entry ".$entry;
		}

		public function allocate(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_resources", $id, "Resource");

			$table = $request->bodyString("table", "", false);
			$entry = $request->bodyString("entry", "", false);

			if ($table === "" || $entry === "") {
				throw new BadRequestException("table and entry required", "missing_fields");
			}

			// Upsert: if the pair already exists, just refresh updated_at.
			$existing = SQL::fetch(
				"SELECT id FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ? AND resource = ?",
				$table, $entry, $id
			);

			if ($existing) {
				SQL::update("bigtree_resource_allocation", $existing["id"], ["updated_at" => "NOW()"]);
			} else {
				SQL::insert("bigtree_resource_allocation", [
					"table" => $table,
					"entry" => $entry,
					"resource" => $id,
					"updated_at" => "NOW()",
				]);
			}

			return Response::ok(["table" => $table, "entry" => $entry, "resource" => $id]);
		}

		public function deallocate(Request $request) {
			$id = $request->id();
			$table = $request->bodyString("table", "", false);
			$entry = $request->bodyString("entry", "", false);
			SQL::query(
				"DELETE FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ? AND resource = ?",
				$table, $entry, $id
			);

			return Response::noContent();
		}

		// — helpers —

		private function enforceFolder($user, $folder_id, $min, $action) {
			if (!PermissionService::userHasFolderAccess($user, $folder_id, $min)) {
				throw new AuthorizationException("Insufficient folder permission to $action ($min required)");
			}
		}

		private function detectMime($tmp_path, $declared, $name) {
			if (function_exists("mime_content_type")) {
				$m = @mime_content_type($tmp_path);

				if ($m) {
					return $m;
				}
			}

			if (is_string($declared) && $declared !== "") {
				return $declared;
			}
			$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			$map = [
				"jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png",
				"gif" => "image/gif", "webp" => "image/webp", "svg" => "image/svg+xml",
				"pdf" => "application/pdf", "mp4" => "video/mp4", "webm" => "video/webm",
			];

			return $map[$ext] ?? "application/octet-stream";
		}

		/**
		 * Validate a caller-supplied storage directory. Resource crops/uploads must
		 * land inside the site's files tree — never escape it via "../" or absolute
		 * paths. Returns a normalized, trailing-slashed relative path or throws.
		 *
		 * BigTreeStorage::replace()/store() only sanitize the *file name*, not the
		 * relative path, so this is the gate that keeps the directory in bounds.
		 */
		private static function safeStorageDirectory($raw, string $default): string {
			$dir = trim((string)($raw ?? ""));

			if ($dir === "") {
				$dir = $default;
			}

			// Reject absolute paths, drive letters, UNC/scheme prefixes, NUL bytes.
			if (
				$dir[0] === "/" ||
				strpos($dir, "\0") !== false ||
				strpos($dir, "://") !== false ||
				strpos($dir, "\\") !== false ||
				preg_match('/^[A-Za-z]:/', $dir)
			) {
				throw new BadRequestException("Invalid storage directory", "bad_directory");
			}

			// Reject any traversal segment.
			foreach (explode("/", $dir) as $segment) {
				if ($segment === "..") {
					throw new BadRequestException("Invalid storage directory", "bad_directory");
				}
			}

			// Normalize to a single trailing slash, no leading slash.
			return rtrim($dir, "/") . "/";
		}

		/**
		 * Neutralize active content in an SVG before it is stored. SVGs are served as
		 * static files from the site origin, so a stored <script>/on*-handler is a
		 * stored-XSS vector when another admin opens the file URL.
		 *
		 * This is a DOM/allowlist sanitizer (ext-dom, no extra dependency): the SVG is
		 * parsed as XML with external-entity loading disabled (LIBXML_NONET, no DTD
		 * load — prevents XXE), then the tree is walked default-deny. Any element not on
		 * the SVG allowlist is dropped; any attribute not on the allowlist (and every
		 * on*-handler) is stripped; href/xlink:href are scheme-checked (only #fragment
		 * and relative refs survive — javascript:/data: are neutralized). The document
		 * is then re-serialized.
		 *
		 * It is intentionally destructive toward active content — a sanitized SVG may
		 * lose interactivity, which is the correct trade-off for user-uploaded assets.
		 * It fails closed: if the input is not well-formed XML it is replaced with a
		 * neutral empty SVG rather than returned unchanged.
		 *
		 * Default-deny is deliberate (cf. the SPA's dompurify posture): new attack
		 * elements/attributes are rejected because they are not allowlisted, without
		 * needing a new denylist rule. If this proves too brittle for legitimate SVGs,
		 * escalate to enshrined/svg-sanitize.
		 */
		private static function sanitizeSvg(string $svg): string {
			$neutral = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

			if (trim($svg) === "") {
				return $neutral;
			}

			$doc = new \DOMDocument();
			$prev = libxml_use_internal_errors(true);
			// LIBXML_NONET: no network access. Crucially we do NOT pass LIBXML_DTDLOAD
			// or LIBXML_NOENT, so external/parameter entities are never loaded or
			// expanded (XXE-safe). NONET is belt-and-braces for any inline DTD.
			$loaded = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
			libxml_clear_errors();
			libxml_use_internal_errors($prev);

			// Fail closed on malformed input or a non-<svg> root.
			if ($loaded === false || $doc->documentElement === null) {
				return $neutral;
			}

			if (strtolower($doc->documentElement->localName) !== "svg") {
				return $neutral;
			}

			// Reject any DTD (it cannot carry active content here, but it has no place
			// in a stored asset and is an entity-expansion surface).
			if ($doc->doctype !== null) {
				return $neutral;
			}

			self::sanitizeSvgNode($doc->documentElement);

			$out = $doc->saveXML($doc->documentElement);

			if ($out === false) {
				return $neutral;
			}

			return $out;
		}

		/**
		 * Recursively apply the SVG allowlist to a DOM element: drop disallowed child
		 * elements (script/foreignObject/style/anything unknown), strip disallowed and
		 * on* attributes, and scheme-check href/xlink:href. Comments/PIs are removed.
		 */
		private static function sanitizeSvgNode(\DOMElement $element): void {
			// Allowlisted SVG element names (lower-cased localName). Default-deny:
			// anything not here is removed. Deliberately excludes script, style,
			// foreignObject, animate, set, handler, and any HTML element.
			static $allowed_elements = [
				"svg", "g", "defs", "symbol", "use", "title", "desc", "metadata",
				"path", "rect", "circle", "ellipse", "line", "polyline", "polygon",
				"text", "tspan", "textpath", "a", "image",
				"lineargradient", "radialgradient", "stop", "pattern",
				"clippath", "mask", "marker", "filter",
				"fegaussianblur", "feoffset", "feblend", "feflood", "femerge",
				"femergenode", "fecolormatrix", "fecomposite", "fecomponenttransfer",
				"fefunca", "fefuncr", "fefuncg", "fefuncb", "fedropshadow", "femorphology",
				"switch", "view",
			];

			// Attributes that are always dropped regardless of element. href/xlink:href
			// are handled separately (scheme-checked rather than dropped).
			$to_remove = [];
			$to_recheck_href = [];

			foreach (iterator_to_array($element->attributes ?? []) as $attr) {
				/** @var \DOMAttr $attr */
				$name = strtolower($attr->localName);
				$prefixed = strtolower($attr->nodeName);

				// Strip every on* event handler (onload, onclick, onmouseover, …).
				if (strpos($name, "on") === 0) {
					$to_remove[] = $attr;

					continue;
				}

				// Scheme-check href / xlink:href instead of trusting it. localName is
				// "href" when xlink is a declared namespace; when xlink is undeclared
				// libxml keeps the literal "xlink:href" name, so match that too.
				if ($name === "href" || substr($prefixed, -5) === ":href") {
					$to_recheck_href[] = $attr;

					continue;
				}

				// Drop xmlns:* that map to non-SVG/xlink namespaces is overkill; allow
				// declarations through (they carry no executable content). Everything
				// else (presentation attributes, geometry, etc.) is allowed — the
				// dangerous surface is handlers + URL schemes, both handled above.
			}

			foreach ($to_remove as $attr) {
				$element->removeAttributeNode($attr);
			}

			foreach ($to_recheck_href as $attr) {
				if (!self::isSafeSvgUrl($attr->value)) {
					// Neutralize to an inert fragment ref rather than leave it.
					$attr->value = "#";
				}
			}

			// <animate>/<set> can target href via attributeName — even though those
			// elements are not allowlisted (so they're dropped below), defensively
			// neutralize a script-ish "to"/"from"/"values" if one ever slips in.
			$attr_name = strtolower((string)$element->getAttribute("attributeName"));

			if ($attr_name === "href" || $attr_name === "xlink:href") {
				foreach (["to", "from", "values"] as $val_attr) {
					if ($element->hasAttribute($val_attr) && !self::isSafeSvgUrl($element->getAttribute($val_attr))) {
						$element->setAttribute($val_attr, "#");
					}
				}
			}

			// Walk children: drop disallowed elements, comments and PIs.
			foreach (iterator_to_array($element->childNodes) as $child) {
				if ($child instanceof \DOMElement) {
					$child_name = strtolower($child->localName);

					if (!in_array($child_name, $allowed_elements, true)) {
						$element->removeChild($child);

						continue;
					}

					self::sanitizeSvgNode($child);

					continue;
				}

				if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
					$element->removeChild($child);
				}
			}
		}

		/**
		 * A URL is safe to keep in an SVG href/xlink:href only if it is an in-document
		 * fragment (#id) or a relative path. Absolute schemes are rejected — including
		 * javascript:, data:, vbscript: — after stripping whitespace/control chars and
		 * decoding any HTML entities the parser may have left.
		 */
		private static function isSafeSvgUrl(string $url): bool {
			// Decode entities (DOM gives us text already decoded, but be defensive)
			// and strip ASCII whitespace/control characters used to obfuscate schemes
			// e.g. "java\nscript:" or "  javascript:".
			$value = Sanitize::decodeEntities($url);
			$value = preg_replace('/[\x00-\x20]+/', "", $value);

			if ($value === "" || $value === null) {
				return true;
			}

			// Pure fragment reference is always safe.
			if ($value[0] === "#") {
				return true;
			}

			// If there's a scheme (something before a ":" that isn't part of a path),
			// only allow it when there is no scheme at all (relative URL). Any explicit
			// scheme is rejected — we don't need http(s) for stored SVG refs.
			if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
				return false;
			}

			return true;
		}

		/**
		 * If an upload is an SVG (by the name it will be stored under, or its detected
		 * mime), rewrite the temp file in place with active content stripped before it
		 * reaches storage. No-op for any non-SVG upload.
		 *
		 * Wired into both the resource-replace passthrough (reachable today for a
		 * legacy-uploaded .svg resource, which has is_image="") and the generic-file
		 * upload branch (currently unreachable for SVG — the image branch's
		 * BigTreeImage gate rejects SVG first — but live the moment SVG storage is ever
		 * enabled there).
		 */
		private static function sanitizeSvgUpload(string $tmp_path, string $stored_name, string $mime): void {
			$is_svg = $mime === "image/svg+xml"
				|| strtolower(pathinfo($stored_name, PATHINFO_EXTENSION)) === "svg";

			if (!$is_svg) {
				return;
			}

			$raw = @file_get_contents($tmp_path);

			if ($raw === false) {
				return;
			}

			@file_put_contents($tmp_path, self::sanitizeSvg($raw));
		}

		/**
		 * Compose the BigTreeImage settings dict for an image upload. Pulls the
		 * default media preset from JSON config, layers in any caller-supplied
		 * settings, and pins the storage directory. Mirrors the legacy admin's
		 * dropzone-upload-image.php behavior.
		 */
		private function buildImageUploadSettings(array $user_settings) {
			$media = BigTreeJSONDB::get("config", "media-settings");
			$preset = is_array($media["presets"]["default"] ?? null) ? $media["presets"]["default"] : [];

			// Caller-supplied keys override the preset, then we force the directory.
			// The "directory" comes from the request body (settings JSON) on the
			// upload path, so it must be validated the same way crop() validates its
			// directory — storage only sanitizes the file name, not the path.
			$settings = array_merge($preset, $user_settings);
			$settings["directory"] = self::safeStorageDirectory(
				$user_settings["directory"] ?? ($preset["directory"] ?? null),
				"files/resources/"
			);

			foreach (["crops", "thumbs", "center_crops"] as $key) {
				if (!isset($settings[$key]) || !is_array($settings[$key])) {
					$settings[$key] = [];
				}
			}

			return $settings;
		}

		/**
		 * Run a stored image through the full derivative pipeline and return its
		 * [$crop_prefixes, $thumb_prefixes] maps. filterGeneratableCrops drops
		 * crops larger than the source; processCrops handles exact-dimension crops
		 * and every top-level center_crop, returning a registry of crops whose
		 * dimensions didn't match, which we auto-center-crop (the legacy admin made
		 * the user draw those by hand) so every recorded prefix has a real file.
		 * The internal-only `list-preview/` crop is stripped from the returned crop
		 * map — consumers shouldn't see it in the resource's crops.
		 */
		private function generateDerivatives(BigTreeImage $image): array {
			$image->filterGeneratableCrops();
			$this->ensureListPreviewCrop($image);

			$image->processThumbnails();
			$pending = $image->processCrops();
			$this->autoProcessCropRegistry($image, is_array($pending) ? $pending : []);

			[$crop_prefixes, $thumb_prefixes] = $this->buildResourcePrefixes($image);
			unset($crop_prefixes["list-preview/"]);

			return [$crop_prefixes, $thumb_prefixes];
		}

		/**
		 * Append the 100×100 `list-preview/` center crop that the file manager
		 * uses for grid thumbnails. If the source image is smaller than 100px on
		 * either side, fall back to a square sized to the smallest dimension so
		 * we still get *something* rather than failing the upload.
		 */
		private function ensureListPreviewCrop(BigTreeImage $image) {
			foreach ($image->Settings["center_crops"] as $crop) {
				if (($crop["prefix"] ?? "") === "list-preview/") {
					return;
				}
			}

			$min_dim = min((int)$image->Width, (int)$image->Height);
			$size = $min_dim > 0 && $min_dim < 100 ? $min_dim : 100;

			$image->Settings["center_crops"][] = [
				"prefix" => "list-preview/",
				"width" => $size,
				"height" => $size,
			];
		}

		/**
		 * Generate each crop in BigTreeImage::processCrops()'s pending registry
		 * as a center crop, plus any thumbs / center_crops nested under it. The
		 * legacy admin would hand these back to the browser for a manual draw;
		 * for file-manager uploads we crop unattended so the recorded prefixes
		 * always resolve to real files. The image MUST be larger than every
		 * crop in the registry — `filterGeneratableCrops()` enforces that.
		 */
		private function autoProcessCropRegistry(BigTreeImage $image, array $registry) {
			$retina = !empty($image->Settings["retina"]);
			$directory = $image->Settings["directory"];

			foreach ($registry as $crop) {
				$width = (int)($crop["width"] ?? 0);
				$height = (int)($crop["height"] ?? 0);

				if ($width <= 0 || $height <= 0) {
					continue;
				}

				if (!empty($crop["prefix"])) {
					$temp = $image->getTempFileName();
					$image->centerCrop($temp, $width, $height, $retina, !empty($crop["grayscale"]));
					$image->Storage->replace(
						$temp,
						$crop["prefix"] . $image->StoredName,
						$directory,
						true,
						false
					);
				}

				if (is_array($crop["thumbs"] ?? null)) {
					foreach ($crop["thumbs"] as $thumb) {
						if (empty($thumb["prefix"])) {
							continue;
						}
						// Scale the requested thumb against the crop's box, not
						// the source image — matches the prefix dimensions we
						// publish via buildResourcePrefixes / getThumbnailSize.
						$size = $image->getThumbnailSize(
							$thumb["width"] ?? 0,
							$thumb["height"] ?? 0,
							$width,
							$height
						);
						$temp = $image->getTempFileName();
						$image->thumbnail($temp, $size["width"], $size["height"], $retina, !empty($thumb["grayscale"]));
						$image->Storage->replace(
							$temp,
							$thumb["prefix"] . $image->StoredName,
							$directory,
							true,
							false
						);
					}
				}

				if (is_array($crop["center_crops"] ?? null)) {
					foreach ($crop["center_crops"] as $center_crop) {
						if (empty($center_crop["prefix"])) {
							continue;
						}
						$temp = $image->getTempFileName();
						$image->centerCrop(
							$temp,
							(int)($center_crop["width"] ?? 0),
							(int)($center_crop["height"] ?? 0),
							$retina,
							!empty($center_crop["grayscale"])
						);
						$image->Storage->replace(
							$temp,
							$center_crop["prefix"] . $image->StoredName,
							$directory,
							true,
							false
						);
					}
				}
			}
		}

		/**
		 * Walk a BigTreeImage's processed settings to produce the prefix → size
		 * maps stored in bigtree_resources.crops and bigtree_resources.thumbs.
		 * Mirrors core/admin/modules/files/process/_resource-prefixes.php.
		 */
		private function buildResourcePrefixes(BigTreeImage $image) {
			$crop_prefixes = [];
			$thumb_prefixes = [];

			foreach ($image->Settings["crops"] as $crop) {
				if (!empty($crop["prefix"])) {
					$crop_prefixes[$crop["prefix"]] = ["width" => $crop["width"], "height" => $crop["height"]];
				}

				if (is_array($crop["thumbs"] ?? null)) {
					foreach ($crop["thumbs"] as $thumb) {
						if (!empty($thumb["prefix"])) {
							$crop_prefixes[$thumb["prefix"]] = $image->getThumbnailSize($thumb["width"], $thumb["height"], $crop["width"], $crop["height"]);
						}
					}
				}

				if (is_array($crop["center_crops"] ?? null)) {
					foreach ($crop["center_crops"] as $center_crop) {
						if (!empty($center_crop["prefix"])) {
							$crop_prefixes[$center_crop["prefix"]] = ["width" => $center_crop["width"], "height" => $center_crop["height"]];
						}
					}
				}
			}

			foreach ($image->Settings["center_crops"] as $crop) {
				if (!empty($crop["prefix"])) {
					$crop_prefixes[$crop["prefix"]] = ["width" => $crop["width"], "height" => $crop["height"]];
				}

				if (is_array($crop["thumbs"] ?? null)) {
					foreach ($crop["thumbs"] as $thumb) {
						if (!empty($thumb["prefix"])) {
							$crop_prefixes[$thumb["prefix"]] = $image->getThumbnailSize($thumb["width"], $thumb["height"], $crop["width"], $crop["height"]);
						}
					}
				}
			}

			foreach ($image->Settings["thumbs"] as $thumb) {
				if (!empty($thumb["prefix"])) {
					$thumb_prefixes[$thumb["prefix"]] = $image->getThumbnailSize($thumb["width"], $thumb["height"]);
				}
			}

			return [$crop_prefixes, $thumb_prefixes];
		}

		private function insertResource(array $data) {
			$data["date"] = date("Y-m-d H:i:s");

			// Encode JSON columns
			foreach (["crops", "thumbs", "video_data", "metadata"] as $k) {
				if (is_array($data[$k] ?? null)) {
					$data[$k] = json_encode($data[$k]);
				}
			}

			return (int)SQL::insert("bigtree_resources", $data);
		}

		private function isAncestor($candidate_ancestor_id, $candidate_descendant_id) {
			$current = (int)$candidate_descendant_id;
			$seen = [];

			while ($current > 0) {
				if ($current === (int)$candidate_ancestor_id) {
					return true;
				}

				if (isset($seen[$current])) {
					return false;
				}
				$seen[$current] = true;
				$row = SQL::fetch("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $current);

				if (!$row) {
					return false;
				}
				$current = (int)$row["parent"];
			}

			return false;
		}

		/**
		 * Parse a YouTube or Vimeo URL and fetch its metadata via the public
		 * oembed/v2 endpoints. Returns the same shape the legacy admin built
		 * (service, id, title, description, image, url, user_*, dimensions,
		 * duration, embed) so existing `video_data` consumers keep working.
		 */
		private function extractVideoMetadata($url) {
			if (strpos($url, "youtu.be") !== false || strpos($url, "youtube.com") !== false) {
				return $this->extractYouTubeMetadata($url);
			}

			if (strpos($url, "vimeo.com") !== false) {
				return $this->extractVimeoMetadata($url);
			}

			throw new BadRequestException("URL is not a recognized YouTube or Vimeo URL", "invalid_video_url");
		}

		private function extractYouTubeMetadata($url) {
			// Strip everything except the v= query param so quirky URLs (timestamps,
			// playlists) still resolve to the canonical watch URL the regex expects.
			$parsed = parse_url($url);

			if (!empty($parsed["query"])) {
				parse_str($parsed["query"], $params);

				if (!empty($params["v"])) {
					$url = ($parsed["scheme"] ?: "https") . "://" . $parsed["host"] . $parsed["path"] . "?v=" . $params["v"];
				}
			}

			$pattern = '%(?:youtu\.be/|youtube\.com/(?:embed/|v/|.*v=))([\w-]{10,12})%';

			if (!preg_match($pattern, $url, $matches)) {
				throw new BadRequestException("Could not find a video id in the YouTube URL", "invalid_video_url");
			}

			$video_id = $matches[1];
			$oembed_raw = BigTree::cURL("https://www.youtube.com/oembed?url=" . urlencode("https://youtube.com/watch?v=" . $video_id));
			$oembed = json_decode($oembed_raw, true);

			if (empty($oembed["title"])) {
				throw new BadRequestException("YouTube did not return metadata for that video", "video_metadata_unavailable");
			}

			return [
				"service" => "YouTube",
				"id" => $video_id,
				"title" => $oembed["title"],
				"description" => null,
				"image" => $oembed["thumbnail_url"] ?? null,
				"url" => "https://youtube.com/watch?v=" . $video_id,
				"user_id" => null,
				"user_name" => $oembed["author_name"] ?? null,
				"user_url" => $oembed["author_url"] ?? null,
				"upload_date" => null,
				"height" => null,
				"width" => null,
				"duration" => null,
				"embed" => $oembed["html"] ?? null,
			];
		}

		private function extractVimeoMetadata($url) {
			$pieces = explode("/", rtrim($url, "/"));
			$video_id = end($pieces);

			if (!ctype_digit((string)$video_id)) {
				throw new BadRequestException("Could not find a video id in the Vimeo URL", "invalid_video_url");
			}

			$raw = BigTree::cURL("https://vimeo.com/api/v2/video/" . $video_id . ".json");
			$data = json_decode($raw, true);

			if (!is_array($data) || empty($data[0]["title"])) {
				throw new BadRequestException("Vimeo did not return metadata for that video", "video_metadata_unavailable");
			}
			$v = $data[0];
			$image = $v["thumbnail_large"] ?: ($v["thumbnail_medium"] ?: ($v["thumbnail_small"] ?? null));

			return [
				"service" => "Vimeo",
				"id" => (string)$video_id,
				"title" => $v["title"],
				"description" => $v["description"] ?? null,
				"image" => $image,
				"url" => $v["url"] ?? ("https://vimeo.com/" . $video_id),
				"user_id" => $v["user_id"] ?? null,
				"user_name" => $v["user_name"] ?? null,
				"user_url" => $v["user_url"] ?? null,
				"upload_date" => $v["upload_date"] ?? null,
				"height" => $v["height"] ?? null,
				"width" => $v["width"] ?? null,
				"duration" => $v["duration"] ?? null,
				"embed" => '<iframe src="https://player.vimeo.com/video/' . $video_id . '?byline=0&portrait=0" width="' . ($v["width"] ?? 640) . '" height="' . ($v["height"] ?? 360) . '" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',
			];
		}

		private function folderBreadcrumb($folder_id) {
			$out = [];
			$current = (int)$folder_id;

			while ($current > 0) {
				$row = SQL::fetch("SELECT id, parent, name FROM bigtree_resource_folders WHERE id = ?", $current);

				if (!$row) {
					break;
				}
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
				"is_image" => Flag::isOn($r["is_image"]),
				"is_video" => Flag::isOn($r["is_video"]),
				"height" => $r["height"] !== null ? (int)$r["height"] : null,
				"width" => $r["width"] !== null ? (int)$r["width"] : null,
				"size" => $r["size"] !== null ? (int)$r["size"] : null,
				"date" => $r["date"],
			];

			if ($detailed) {
				$base["location"] = $r["location"] ?? "";
				$base["md5"] = $r["md5"] ?? "";
				$base["metadata"] = json_decode($r["metadata"] ?? "{}", true) ?: new \stdClass();
				$base["crops"] = $this->presentPrefixMap($r["file"], $r["crops"] ?? "{}");
				$base["thumbs"] = $this->presentPrefixMap($r["file"], $r["thumbs"] ?? "{}");
				$base["video_data"] = json_decode($r["video_data"] ?? "{}", true) ?: new \stdClass();
			}

			return $base;
		}

		/**
		 * Decode a JSON prefix → {width, height, ...} column and augment each
		 * entry with the derived file URL (`prefixFile(originalFile, prefix)`)
		 * unless the row already carries its own `file` (user-added crops from
		 * the crop endpoint store one explicitly). Returns the map verbatim if
		 * the column is empty so the JSON envelope renders as `{}`, not `[]`.
		 */
		private function presentPrefixMap($source_file, $json_blob) {
			$decoded = json_decode((string)$json_blob ?: "{}", true);

			if (!is_array($decoded) || empty($decoded)) {
				return new \stdClass();
			}

			$out = [];

			foreach ($decoded as $prefix => $entry) {
				if (!is_array($entry)) {
					continue;
				}
				$entry["prefix"] = (string)$prefix;

				if (empty($entry["file"]) && $source_file) {
					$entry["file"] = BigTree::prefixFile($source_file, (string)$prefix);
				}

				$out[(string)$prefix] = $entry;
			}

			return $out ?: new \stdClass();
		}
	}
