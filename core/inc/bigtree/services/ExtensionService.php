<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Jwt;
	use BigTree\Api\Pagination;
	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree;
	use BigTreeAdmin;
	use BigTreeAutoModule;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Extension lifecycle: list / get / delete, plus the developer tooling ported
	 * from the legacy admin's extensions module — update detection, refresh hooks
	 * cache, install (upload), in-place upgrade, and the build/packaging wizard.
	 *
	 * The heavy domain logic (installExtension, cacheHooks) lives on BigTreeAdmin;
	 * we orchestrate a fresh instance the same way other services do
	 * (AutoModuleService, SystemService) rather than re-implementing it.
	 */
	class ExtensionService {
		public function list(Request $request) {
			$sort = (string)($request->query["sort"] ?? "name");
			$dir = $sort[0] === "-" ? "DESC" : "ASC";
			$col = ltrim($sort, "-");
			$rows = BigTreeJSONDB::getAll("extensions", $col, $dir);

			return Response::ok(array_map([$this, "present"], $rows));
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$ext = BigTreeAdmin::getExtension($id);

			if (!$ext) {
				throw new NotFoundException("Extension $id not found");
			}
			return Response::ok($this->present($ext));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");
			$ext = BigTreeAdmin::getExtension($id);

			if (!$ext) {
				throw new NotFoundException("Extension $id not found");
			}

			$manifest = $ext["manifest"] ?? [];
			$ext_id = $manifest["id"] ?? "";

			if ($ext_id !== "") {
				BigTree::deleteDirectory(SITE_ROOT . "extensions/$ext_id/");
				BigTree::deleteDirectory(SERVER_ROOT . "extensions/$ext_id/");
			}

			foreach ($manifest["components"] ?? [] as $type => $list) {
				if ($type === "tables") {
					SQL::query("SET SESSION foreign_key_checks = 0");

					foreach ((array)$list as $table => $_) {
						SQL::query("DROP TABLE IF EXISTS `" . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . "`");
					}

					SQL::query("SET SESSION foreign_key_checks = 1");
				} else {
					foreach ((array)$list as $item) {
						if (isset($item["id"])) {
							BigTreeJSONDB::delete(str_replace("_", "-", $type), $item["id"]);
						}
					}
				}
			}

			BigTreeJSONDB::delete("extensions", $id);

			return Response::noContent();
		}

		// POST /extensions/recache-hooks — rebuild cache/bigtree-hooks.json from every
		// installed extension's hooks/ directory (legacy recache-hooks.php).
		public function recacheHooks(Request $request) {
			$admin = new BigTreeAdmin();
			$admin->cacheHooks();

			return Response::ok(["status" => "ok"]);
		}

		// GET /extensions/updates — ask the official extension registry for the latest
		// revision of each installed extension and flag the ones with a newer version
		// (legacy extensions/default.php). Read-only; never writes.
		public function updates(Request $request) {
			$extensions = BigTreeAdmin::getExtensions();
			$query = [];

			foreach ($extensions as $extension) {
				$query[] = "extensions[]=" . urlencode($extension["id"]);
			}

			$version_info = [];

			if (count($query) > 0) {
				$raw = BigTree::cURL(
					"https://www.bigtreecms.org/ajax/extensions/version/?" . implode("&", $query),
					false,
					[CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 6]
				);
				$version_info = array_filter((array)@json_decode($raw, true));
			}

			$out = [];

			foreach ($extensions as $extension) {
				$id = $extension["id"];
				$manifest = $this->localManifest($id);
				$local_revision = (int)($manifest["revision"] ?? ($extension["manifest"]["revision"] ?? 0));
				$remote = $version_info[$id] ?? null;
				$remote_revision = (int)($remote["revision"] ?? 0);
				$update_available = $remote && $remote_revision > $local_revision;

				$out[] = [
					"id" => $id,
					"name" => $extension["name"] ?? $id,
					"version" => $extension["version"] ?? ($manifest["version"] ?? ""),
					"update_available" => $update_available,
					"available_version" => $update_available ? (string)($remote["version"] ?? "") : null,
					"compatibility" => $update_available ? (string)($remote["compatibility"] ?? "") : null,
				];
			}

			return Response::ok($out);
		}

		// POST /extensions/install/unpack — accept an uploaded extension zip, stage it
		// under cache/package/, validate the manifest, and report install warnings/errors
		// WITHOUT committing anything (legacy install/unpack.php). The caller reviews the
		// result, then POSTs /extensions/install/process to commit.
		public function installUnpack(Request $request) {
			foreach (["cache/", "extensions/", "site/extensions/"] as $dir) {
				if (!is_writable(SERVER_ROOT . $dir)) {
					throw new BadRequestException("The /$dir directory must be writable to install extensions.", "not_writable");
				}
			}

			$file_set = $request->file("file");

			if (!$file_set) {
				throw new BadRequestException("Missing 'file' upload", "missing_file");
			}

			$file = $file_set[0];

			if (!empty($file["error"]) || empty($file["tmp_name"])) {
				throw new BadRequestException("File upload failed.", "upload_failed");
			}

			// Clean + recreate the staging area, then unzip into it.
			$cache_root = SERVER_ROOT . "cache/package/";
			BigTree::deleteDirectory($cache_root);
			BigTree::makeDirectory($cache_root);

			include_once BigTree::path("inc/lib/pclzip.php");
			$zip = new \PclZip($file["tmp_name"]);

			// GitHub-style archives nest everything under a single root folder; strip it.
			$zip_root = \BigTreeUpdater::zipRoot($zip);

			if ($zip_root) {
				$files = $zip->extract(PCLZIP_OPT_PATH, $cache_root, PCLZIP_OPT_REMOVE_PATH, $zip_root);
			} else {
				$files = $zip->extract(PCLZIP_OPT_PATH, $cache_root);
			}

			if (!$files) {
				BigTree::deleteDirectory($cache_root);

				throw new BadRequestException("The file uploaded is either not a zip file or is corrupt.", "bad_zip");
			}

			$manifest = json_decode((string)@file_get_contents($cache_root . "manifest.json"), true);

			// Validate it's a real extension. The alnum id check matters for security —
			// the id becomes a directory name, so we reject anything that could traverse.
			if (
				!is_array($manifest)
				|| ($manifest["type"] ?? "") !== "extension"
				|| empty($manifest["id"])
				|| empty($manifest["title"])
				|| !ctype_alnum(str_replace([".", "_", "-"], "", (string)$manifest["id"]))
			) {
				BigTree::deleteDirectory($cache_root);

				throw new BadRequestException("The zip file uploaded does not appear to be a BigTree extension.", "not_extension");
			}

			if (BigTreeJSONDB::exists("extensions", $manifest["id"])) {
				BigTree::deleteDirectory($cache_root);

				throw new ConflictException("An extension with the id " . $manifest["id"] . " is already installed.", "already_installed");
			}

			$warnings = [];
			$errors = [];

			foreach ((array)($manifest["components"]["tables"] ?? []) as $table => $create_statement) {
				if (BigTree::tableExists((string)$table)) {
					$warnings[] = "A table named “$table” already exists — it will be overwritten.";
				}
			}

			foreach ((array)($manifest["files"] ?? []) as $f) {
				if (!BigTree::isDirectoryWritable(SERVER_ROOT . $f)) {
					$errors[] = "Cannot write to $f — please make the directory or file writable.";
				} elseif (file_exists(SERVER_ROOT . $f)) {
					if (!is_writable(SERVER_ROOT . $f)) {
						$errors[] = "Cannot overwrite existing file: $f — please make the file writable or delete it.";
					} else {
						$warnings[] = "A file already exists at $f — it will be overwritten.";
					}
				}
			}

			return Response::ok([
				"manifest" => [
					"id" => (string)$manifest["id"],
					"title" => (string)$manifest["title"],
					"version" => (string)($manifest["version"] ?? ""),
					"author" => $manifest["author"] ?? null,
				],
				"warnings" => $warnings,
				"errors" => $errors,
				"ready" => count($errors) === 0,
			]);
		}

		// POST /extensions/install/process — commit the staged package (legacy
		// install/process.php): installExtension() copies files + runs install SQL, then
		// the extension's install.php (if any) runs and its output is returned.
		public function installProcess(Request $request) {
			$cache_root = SERVER_ROOT . "cache/package/";
			$manifest = json_decode((string)@file_get_contents($cache_root . "manifest.json"), true);

			if (!is_array($manifest) || empty($manifest["id"])) {
				throw new BadRequestException("No staged extension to install — upload a package first.", "no_staged_package");
			}

			if (BigTreeJSONDB::exists("extensions", $manifest["id"])) {
				throw new ConflictException("An extension with the id " . $manifest["id"] . " is already installed.", "already_installed");
			}

			$admin = new BigTreeAdmin();
			$admin->installExtension($manifest);

			// Run the optional install.php with the legacy globals in scope so existing
			// install scripts keep working.
			$output = "";
			$install_file = SERVER_ROOT . "extensions/" . $manifest["id"] . "/install.php";

			if (file_exists($install_file)) {
				global $bigtree, $cms;
				ob_start();
				include $install_file;
				$output = (string)ob_get_clean();
			}

			return Response::created([
				"id" => (string)$manifest["id"],
				"output" => $output,
			], null);
		}

		// POST /extensions/{id}/upgrade — download the latest package from the registry
		// and reinstall it over the existing one (installExtension upgrade-mode, which
		// drops old components and applies the new manifest + SQL revisions).
		//
		// Scope note: the legacy admin's FTP/SFTP path (for installs where the web server
		// can't write outside extensions/) is intentionally NOT ported — this requires a
		// writable extensions/{id}/ (the same requirement as Install). Installs that need
		// FTP should upload the new version via the installer instead.
		public function upgrade(Request $request) {
			$id = $request->routeParam("id");
			$ext = BigTreeAdmin::getExtension($id);

			if (!$ext) {
				throw new NotFoundException("Extension $id not found");
			}

			$ext_dir = SERVER_ROOT . "extensions/" . str_replace(["/", "\\", ".."], "", $id) . "/";

			if (!is_writable($ext_dir)) {
				throw new BadRequestException("The extensions/$id directory isn't writable — upload the new version via the installer instead.", "not_writable");
			}

			$old_manifest = $this->localManifest($id) ?: (array)($ext["manifest"] ?? []);

			// Ask the registry for this extension's download URL.
			$raw = BigTree::cURL(
				"https://www.bigtreecms.org/ajax/extensions/version/?extensions[]=" . urlencode($id),
				false,
				[CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 6]
			);
			$info = array_filter((array)@json_decode($raw, true));
			$download_url = $info[$id]["github_url"] ?? "";

			if ($download_url === "") {
				throw new BadRequestException("No download is available for this extension from the registry.", "no_download");
			}

			// Download the package to a temp zip.
			$zip_path = SERVER_ROOT . "cache/extension-upgrade-" . preg_replace('/[^a-zA-Z0-9._-]/', "", $id) . ".zip";
			@unlink($zip_path);
			$fh = @fopen($zip_path, "w");

			if (!$fh) {
				throw new BadRequestException("Could not write to cache/ to download the update.", "download_failed", 500);
			}

			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL => $download_url,
				CURLOPT_TIMEOUT => 300,
				CURLOPT_FILE => $fh,
				CURLOPT_FOLLOWLOCATION => true,
			]);
			curl_exec($curl);
			curl_close($curl);
			fclose($fh);

			// Extract into staging.
			$stage = SERVER_ROOT . "cache/extension-upgrade-stage/";
			BigTree::deleteDirectory($stage);
			BigTree::makeDirectory($stage);

			include_once BigTree::path("inc/lib/pclzip.php");
			$zip = new \PclZip($zip_path);
			$zip_root = \BigTreeUpdater::zipRoot($zip);
			$files = $zip_root
				? $zip->extract(PCLZIP_OPT_PATH, $stage, PCLZIP_OPT_REMOVE_PATH, $zip_root)
				: $zip->extract(PCLZIP_OPT_PATH, $stage);

			if (!$files) {
				BigTree::deleteDirectory($stage);
				@unlink($zip_path);

				throw new BadRequestException("The downloaded update is not a valid zip.", "bad_zip");
			}

			$new_manifest = json_decode((string)@file_get_contents($stage . "manifest.json"), true);

			if (!is_array($new_manifest) || ($new_manifest["id"] ?? "") !== $id) {
				BigTree::deleteDirectory($stage);
				@unlink($zip_path);

				throw new BadRequestException("The downloaded package does not match this extension.", "manifest_mismatch");
			}

			// Replace the extension's files (extensions are self-contained under
			// extensions/{id}/), then commit via installExtension's upgrade mode.
			BigTree::deleteDirectory($ext_dir);
			BigTree::makeDirectory($ext_dir);

			foreach ((array)BigTree::directoryContents($stage) as $file) {
				if (is_file($file)) {
					BigTree::moveFile($file, $ext_dir . str_replace($stage, "", $file));
				}
			}

			BigTree::deleteDirectory($stage);
			@unlink($zip_path);

			$admin = new BigTreeAdmin();
			$admin->installExtension($new_manifest, $old_manifest);

			$output = "";
			$update_file = $ext_dir . "update.php";

			if (file_exists($update_file)) {
				global $bigtree, $cms;
				ob_start();
				include $update_file;
				$output = (string)ob_get_clean();
			}

			$admin->cacheHooks();

			return Response::ok([
				"id" => $id,
				"version" => (string)($new_manifest["version"] ?? ""),
				"output" => $output,
			]);
		}

		// Seconds a build download token stays valid (mirrors the backup download TTL).
		const BUILD_DOWNLOAD_TTL = 600;

		// The license catalog the build wizard offers (legacy extensions/_header.php).
		private function availableLicenses(): array
		{
			return [
				"Closed Source" => [
					"Free For Personal Use" => "",
					"Proprietary" => "",
				],
				"Open Source" => [
					"LGPL v2.1" => "http://opensource.org/licenses/LGPL-2.1",
					"LGPL v3" => "http://opensource.org/licenses/LGPL-3.0",
					"GPL v2" => "http://opensource.org/licenses/GPL-2.0",
					"GPL v3" => "http://opensource.org/licenses/GPL-3.0",
					"MIT" => "http://opensource.org/licenses/MIT",
					"BSD 2-Clause" => "http://opensource.org/licenses/BSD-2-Clause",
					"BSD 3-Clause" => "http://opensource.org/licenses/BSD-3-Clause",
					"Apache 2.0" => "http://opensource.org/licenses/Apache-2.0",
					"MPL 2.0" => "http://opensource.org/licenses/MPL-2.0",
				],
			];
		}

		// GET /extensions/build/licenses — the open/closed-source license options the
		// build wizard's details step renders.
		public function buildLicenses(Request $request) {
			return Response::ok($this->availableLicenses());
		}

		// POST /extensions/build/inspect — given the chosen components, infer the tables,
		// files, and extra (custom) field types that should travel with them. Read-only;
		// faithful port of build/save-components.php. The wizard shows the result for the
		// user to trim before packaging.
		public function buildInspect(Request $request) {
			$d = $request->body;
			$admin = new BigTreeAdmin();

			$modules = array_filter((array)($d["modules"] ?? []));
			$templates = array_filter((array)($d["templates"] ?? []));
			$callouts = array_filter((array)($d["callouts"] ?? []));
			$settings = array_filter((array)($d["settings"] ?? []));
			$field_types = array_values(array_filter((array)($d["field_types"] ?? [])));

			$module_groups = [];
			$tables = [];
			$files = [];

			// Which field types are custom (extension-eligible)?
			$custom_field_types = [];

			foreach ($admin->getFieldTypes() as $type) {
				$custom_field_types[$type["id"]] = true;
			}

			$add_table = function ($table) use (&$tables) {
				$table = (string)$table;

				if ($table !== "" && substr($table, 0, 8) !== "bigtree_" && !in_array($table, $tables, true)) {
					$tables[] = $table;
				}
			};

			foreach ($modules as $module_id) {
				$module = $admin->getModule($module_id);

				if (!$module) {
					continue;
				}

				foreach ($admin->getModuleActions($module_id) as $action) {
					$auto = null;

					if (!empty($action["form"])) {
						$auto = BigTreeAutoModule::getForm($action["form"]);

						foreach ((array)($auto["fields"] ?? []) as $field) {
							$type = $field["type"] ?? "";

							if ($type === "list" && ($field["list_type"] ?? "") === "db") {
								$add_table($field["pop-table"] ?? "");
							}

							if ($type === "many-to-many") {
								$add_table($field["mtm-connecting-table"] ?? "");
								$add_table($field["mtm-other-table"] ?? "");
							}

							if (isset($custom_field_types[$type]) && !in_array($type, $field_types, true)) {
								$field_types[] = $type;
							}
						}
					} elseif (!empty($action["view"])) {
						$auto = BigTreeAutoModule::getView($action["view"]);
					} elseif (!empty($action["report"])) {
						$auto = BigTreeAutoModule::getReport($action["report"]);
					}

					if ($auto && !empty($auto["table"])) {
						$add_table($auto["table"]);
					}
				}

				if (!empty($module["group"])) {
					$module_groups[] = $module["group"];
				}

				$route = $module["route"];
				$candidates = [
					"custom/inc/modules/$route.php",
					"custom/inc/required/$route.php",
					"custom/admin/css/$route.css",
					"custom/admin/js/$route.js",
				];

				foreach ($candidates as $rel) {
					if (file_exists(SERVER_ROOT . $rel)) {
						$files[] = SERVER_ROOT . $rel;
					}
				}

				foreach (["custom/admin/modules/$route/", "custom/admin/ajax/$route/", "custom/admin/images/$route/"] as $dir) {
					foreach ((array)BigTree::directoryContents(SERVER_ROOT . $dir) as $file) {
						if (is_file($file)) {
							$files[] = $file;
						}
					}
				}
			}

			foreach ($templates as $template) {
				if (is_dir(SERVER_ROOT . "templates/routed/$template/")) {
					foreach ((array)BigTree::directoryContents(SERVER_ROOT . "templates/routed/$template/") as $c) {
						if (is_file($c)) {
							$files[] = $c;
						}
					}
				} elseif (file_exists(SERVER_ROOT . "templates/basic/$template.php")) {
					$files[] = SERVER_ROOT . "templates/basic/$template.php";
				}

				$template_data = BigTreeCMS::getTemplate($template);

				foreach ((array)($template_data["resources"] ?? []) as $field) {
					$type = $field["type"] ?? "";

					if (isset($custom_field_types[$type]) && !in_array($type, $field_types, true)) {
						$field_types[] = $type;
					}
				}
			}

			foreach ($callouts as $callout) {
				if (file_exists(SERVER_ROOT . "templates/callouts/$callout.php")) {
					$files[] = SERVER_ROOT . "templates/callouts/$callout.php";
				}

				$callout_data = $admin->getCallout($callout);

				foreach ((array)($callout_data["resources"] ?? []) as $field) {
					$type = $field["type"] ?? "";

					if (isset($custom_field_types[$type]) && !in_array($type, $field_types, true)) {
						$field_types[] = $type;
					}
				}
			}

			foreach ($settings as $setting_id) {
				$setting = $admin->getSetting($setting_id);
				$type = $setting["type"] ?? "";

				if (isset($custom_field_types[$type]) && !in_array($type, $field_types, true)) {
					$field_types[] = $type;
				}
			}

			foreach ($field_types as $type) {
				$candidates = [
					"custom/admin/form-field-types/draw/$type.php",
					"custom/admin/form-field-types/process/$type.php",
					"custom/admin/ajax/developer/field-options/$type.php",
					"custom/admin/field-types/$type/draw.php",
					"custom/admin/field-types/$type/process.php",
					"custom/admin/field-types/$type/settings.php",
				];

				foreach ($candidates as $rel) {
					if (file_exists(SERVER_ROOT . $rel)) {
						$files[] = SERVER_ROOT . $rel;
					}
				}
			}

			$id = (string)($d["id"] ?? "");

			if ($id !== "") {
				$ext_dirs = array_merge(
					(array)BigTree::directoryContents(SITE_ROOT . "extensions/$id/"),
					(array)BigTree::directoryContents(SERVER_ROOT . "extensions/$id/")
				);

				foreach ($ext_dirs as $file) {
					if (!is_dir($file) && file_exists($file)) {
						$files[] = $file;
					}
				}
			}

			$rel_files = array_values(array_unique(array_map(function ($f) {
				return str_replace(SERVER_ROOT, "", $f);
			}, $files)));
			sort($rel_files);

			$tables = array_values(array_unique($tables));
			sort($tables);

			return Response::ok([
				"module_groups" => array_values(array_unique($module_groups)),
				"field_types" => array_values(array_unique($field_types)),
				"tables" => $tables,
				"files" => $rel_files,
			]);
		}

		// POST /extensions/build — package the selected components into an extension
		// (faithful port of build/create.php). DESTRUCTIVE: namespaces component ids
		// into the extension (`{id}*{name}`), rewrites references, MOVES files into
		// extensions/{id}/, dumps table structures, computes SQL-revision diffs against
		// any existing package, writes the manifest, zips, and records the extension.
		public function buildPackage(Request $request) {
			$d = $request->body;
			$admin = new BigTreeAdmin();
			$available_licenses = $this->availableLicenses();

			$id = trim((string)($d["id"] ?? ""));

			if (!ctype_alnum(str_replace([".", "-", "_"], "", $id)) || $id === "") {
				throw new BadRequestException("Extension ID may only contain letters, numbers, '.', '-', and '_'.", "invalid_id");
			}

			$title = (string)($d["title"] ?? "");
			$version = (string)($d["version"] ?? "");
			$compatibility = (string)($d["compatibility"] ?? "");
			$description = (string)($d["description"] ?? "");
			$keywords = array_values(array_filter(array_map("trim", (array)($d["keywords"] ?? []))));
			$author = is_array($d["author"] ?? null) ? $d["author"] : ["name" => "", "email" => "", "url" => ""];

			// Resolve the license map from the chosen license identifiers.
			$license_array = [];

			foreach ((array)($d["licenses"] ?? []) as $license) {
				if (isset($available_licenses["Open Source"][$license])) {
					$license_array[$license] = $available_licenses["Open Source"][$license];
				}
			}

			if (!$license_array && !empty($d["license_name"])) {
				$license_array = [(string)$d["license_name"] => (string)($d["license_url"] ?? "")];
			} elseif (!$license_array && !empty($d["license"]) && isset($available_licenses["Closed Source"][$d["license"]])) {
				$license_array = [(string)$d["license"] => $available_licenses["Closed Source"][$d["license"]]];
			}

			$extension_root = SERVER_ROOT . "extensions/$id/";

			if (!file_exists($extension_root)) {
				BigTree::makeDirectory($extension_root);
			}

			$package = [
				"type" => "extension",
				"id" => $id,
				"version" => $version,
				"revision" => 1,
				"compatibility" => $compatibility,
				"title" => $title,
				"description" => $description,
				"keywords" => $keywords,
				"author" => $author,
				"licenses" => $license_array,
				"components" => [
					"module_groups" => [],
					"modules" => [],
					"templates" => [],
					"callouts" => [],
					"settings" => [],
					"feeds" => [],
					"field_types" => [],
					"tables" => [],
				],
			];

			SQL::query("SET foreign_key_checks = 0");

			foreach (array_filter((array)($d["module_groups"] ?? [])) as $group) {
				$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
			}

			foreach (array_filter((array)($d["callouts"] ?? [])) as $callout) {
				if (strpos($callout, "*") === false) {
					BigTreeJSONDB::update("callouts", $callout, ["extension" => $id, "id" => $id . "*" . $callout]);
					$callout = $id . "*" . $callout;
				}

				$package["components"]["callouts"][] = $admin->getCallout($callout);
			}

			foreach (array_filter((array)($d["feeds"] ?? [])) as $feed_id) {
				$feed = BigTreeJSONDB::get("feeds", $feed_id);

				if ($feed && empty($feed["extension"])) {
					BigTreeJSONDB::update("feeds", $feed["id"], ["route" => $id . "/" . $feed["route"], "extension" => $id]);
				}

				$package["components"]["feeds"][] = $feed;
			}

			foreach (array_filter((array)($d["settings"] ?? [])) as $setting) {
				if (strpos($setting, "*") === false) {
					BigTreeJSONDB::update("settings", $setting, ["id" => $id . "*" . $setting, "extension" => $id]);
					$setting = "$id*$setting";
				}

				$package["components"]["settings"][] = $admin->getSetting($setting);
			}

			foreach (array_filter((array)($d["field_types"] ?? [])) as $type) {
				if (strpos($type, "*") === false) {
					BigTreeJSONDB::update("field-types", $type, ["extension" => $id, "id" => $id . "*" . $type]);

					$this->convertFieldTypeUsage($id, $type, "templates", "resources");
					$this->convertFieldTypeUsage($id, $type, "callouts", "resources");
					$this->convertFieldTypeUsage($id, $type, "modules", "fields", "forms");
					$this->convertFieldTypeUsage($id, $type, "modules", "fields", "embeddable-forms");

					foreach (BigTreeJSONDB::getAll("settings") as $setting) {
						if (($setting["type"] ?? "") == $type) {
							BigTreeJSONDB::update("settings", $setting["id"], ["type" => $id . "*" . $type]);
						}
					}

					if (file_exists(SERVER_ROOT . "custom/admin/field-types/$type/")) {
						BigTree::moveFile(SERVER_ROOT . "custom/admin/field-types/$type/draw.php", $extension_root . "field-types/$type/draw.php");
						BigTree::moveFile(SERVER_ROOT . "custom/admin/field-types/$type/process.php", $extension_root . "field-types/$type/process.php");
						BigTree::moveFile(SERVER_ROOT . "custom/admin/field-types/$type/settings.php", $extension_root . "field-types/$type/settings.php");
						@rmdir(SERVER_ROOT . "custom/admin/field-types/$type/");
					} else {
						BigTree::moveFile(SERVER_ROOT . "custom/admin/ajax/developer/field-options/$type.php", $extension_root . "field-types/$type/settings.php");
						BigTree::moveFile(SERVER_ROOT . "custom/admin/form-field-types/draw/$type.php", $extension_root . "field-types/$type/draw.php");
						BigTree::moveFile(SERVER_ROOT . "custom/admin/form-field-types/process/$type.php", $extension_root . "field-types/$type/process.php");
					}

					$type = "$id*$type";
				}

				$package["components"]["field_types"][] = $admin->getFieldType($type);
			}

			foreach (array_filter((array)($d["templates"] ?? [])) as $template) {
				if (strpos($template, "*") === false) {
					BigTreeJSONDB::update("templates", $template, ["extension" => $id, "id" => $id . "*" . $template]);
					$template = "$id*$template";
				}

				$package["components"]["templates"][] = BigTreeCMS::getTemplate($template);
			}

			foreach (array_filter((array)($d["modules"] ?? [])) as $module_id) {
				$module = $admin->getModule($module_id);

				if (!$module) {
					continue;
				}

				$new_route = false;

				if (empty($module["extension"])) {
					$new_route = $id . "*" . $module["route"];
					BigTreeJSONDB::update("modules", $module["id"], ["route" => $new_route, "extension" => $id]);
				}

				$module["actions"] = $admin->getModuleActions($module["id"]);

				if ($new_route) {
					$context = BigTreeJSONDB::getSubset("modules", $module["id"]);

					foreach ($module["actions"] as $a) {
						if (!empty($a["form"])) {
							$form = BigTreeAutoModule::getForm($a["form"]);

							if ($form && !empty($form["return_url"])) {
								$context->update("forms", $a["form"], ["return_url" => str_replace($module["route"] . "/", $new_route . "/", $form["return_url"])]);
							}
						} elseif (!empty($a["view"])) {
							$view = BigTreeAutoModule::getView($a["view"]);

							if ($view && !empty($view["preview_url"])) {
								$context->update("views", $a["view"], ["preview_url" => str_replace($module["route"] . "/", $new_route . "/", $view["preview_url"])]);
							}
						}
					}
				}

				$module["views"] = $admin->getModuleViews("title", $module["id"]);
				$module["forms"] = $admin->getModuleForms("title", $module["id"]);
				$module["embed_forms"] = $admin->getModuleEmbedForms("title", $module["id"]);
				$module["reports"] = $admin->getModuleReports("title", $module["id"]);

				$package["components"]["modules"][] = $module;
			}

			foreach (array_filter((array)($d["tables"] ?? [])) as $table) {
				$table = (string)$table;

				if (!ctype_alnum(str_replace("_", "", $table))) {
					continue;
				}

				$row = SQL::fetch("SHOW CREATE TABLE `$table`");
				$create_statement = str_replace(["\r", "\n"], " ", (string)end($row));
				$create_statement = preg_replace('/(AUTO_INCREMENT\=\d*\s)/', "", $create_statement);
				$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i", "", $create_statement);
				$package["components"]["tables"][$table] = $create_statement;
			}

			$this->relocatePackageFiles($id, (array)($d["files"] ?? []));

			// Diff against an existing package for SQL revision migrations.
			$existing = BigTreeJSONDB::get("extensions", $id);

			if ($existing) {
				$existing_json = $existing["manifest"];
				$revision = $package["revision"] = (int)($existing_json["revision"] ?? 1) + 1;
				$package["sql_revisions"] = (array)($existing_json["sql_revisions"] ?? []);
				$package["sql_revisions"][$revision] = [];

				foreach ((array)($existing_json["components"]["tables"] ?? []) as $table => $create_statement) {
					if (isset($package["components"]["tables"][$table])) {
						$tmp = preg_replace("/CREATE TABLE `([^`]*)`/i", "CREATE TABLE `bigtree_extension_temp`", $create_statement);
						$tmp = preg_replace("/CONSTRAINT `([^`]*)`/i", "", $tmp);
						SQL::query("DROP TABLE IF EXISTS `bigtree_extension_temp`");
						SQL::query($tmp);

						foreach (BigTree::tableCompare("bigtree_extension_temp", $table) as $statement) {
							if (stripos($statement, "auto_increment = ") === false) {
								$package["sql_revisions"][$revision][] = str_replace("`bigtree_extension_temp`", "`$table`", $statement);
							}
						}

						SQL::query("DROP TABLE IF EXISTS `bigtree_extension_temp`");
					} else {
						$package["sql_revisions"][$revision][] = "DROP TABLE IF EXISTS `$table`";
					}
				}

				foreach ($package["components"]["tables"] as $table => $create_statement) {
					if (!isset($existing_json["components"]["tables"][$table])) {
						$package["sql_revisions"][$revision][] = $create_statement;
					}
				}

				$package["sql_revisions"] = array_filter($package["sql_revisions"]);
			}

			BigTree::putFile($extension_root . "manifest.json", BigTree::json($package));

			// Build the downloadable zip (per-id name avoids clobbering the installer's
			// cache/package staging area).
			$zip_path = SERVER_ROOT . "cache/extension-build-" . preg_replace('/[^a-zA-Z0-9._-]/', "", $id) . ".zip";
			@unlink($zip_path);
			@unlink(SERVER_ROOT . "cache/bigtree-form-field-types.json");
			@unlink(SERVER_ROOT . "cache/bigtree-module-class-list.json");

			include_once BigTree::path("inc/lib/pclzip.php");
			$zip = new \PclZip($zip_path);
			$zip->create(BigTree::directoryContents($extension_root), PCLZIP_OPT_REMOVE_PATH, $extension_root);

			if (BigTreeJSONDB::exists("extensions", $id)) {
				BigTreeJSONDB::update("extensions", $id, [
					"name" => $title,
					"version" => $version,
					"last_updated" => date("Y-m-d H:i:s"),
					"manifest" => $package,
				]);
			} else {
				BigTreeJSONDB::insert("extensions", [
					"id" => $id,
					"name" => $title,
					"version" => $version,
					"last_updated" => date("Y-m-d H:i:s"),
					"manifest" => $package,
				]);
			}

			SQL::query("SET foreign_key_checks = 1");
			$admin->cacheHooks();

			return Response::created([
				"id" => $id,
				"download_url" => $this->buildDownloadUrl($id, (int)$request->user->id),
			], null);
		}

		// GET /extensions/build/download?id=&token= — stream the built zip. Public +
		// token-gated so the SPA can use a browser-native download link.
		public function downloadPackage(Request $request) {
			$id = (string)($request->query["id"] ?? "");
			$raw_token = (string)($request->query["token"] ?? "");

			if ($raw_token === "") {
				throw new AuthenticationException("Missing download token", "missing_token");
			}

			try {
				$payload = Pagination::decodeCursor($raw_token, Jwt::currentSecret());
			} catch (\Throwable $e) {
				throw new AuthenticationException("Invalid download token", "invalid_token");
			}

			if (($payload["eid"] ?? "") !== $id) {
				throw new AuthenticationException("Token does not match this package", "token_mismatch");
			}

			if (((int)($payload["exp"] ?? 0)) < time()) {
				throw new AuthenticationException("Download token expired", "token_expired");
			}

			$path = SERVER_ROOT . "cache/extension-build-" . preg_replace('/[^a-zA-Z0-9._-]/', "", $id) . ".zip";

			if (!file_exists($path)) {
				throw new NotFoundException("Package not found — rebuild the extension.", "package_not_found");
			}

			$response = Response::raw(200, []);
			$response->is_envelope = false;
			$response->body = null;
			$response
				->header("Content-Type", "application/zip")
				->header("Content-Disposition", 'attachment; filename="' . $id . '.zip"')
				->header("Content-Length", (string)@filesize($path))
				->header("Cache-Control", "private, no-store")
				->header("X-Content-Type-Options", "nosniff");
			$response->send(null);

			@readfile($path);
			exit;
		}

		// Token scoped to a package id + issuing user, matching the backup download flow.
		private function buildDownloadUrl(string $id, int $user_id): string
		{
			$payload = ["uid" => $user_id, "eid" => $id, "exp" => time() + self::BUILD_DOWNLOAD_TTL];
			$token = Pagination::encodeCursor($payload, Jwt::currentSecret());

			return rtrim(ADMIN_ROOT, "/") . "/api/v1/extensions/build/download?id=" . urlencode($id) . "&token=" . urlencode($token);
		}

		// Rewrite a non-extension field type's id (`type` → `{id}*type`) everywhere it's
		// used (templates/callouts resources, module form fields incl. matrix columns).
		// Port of create.php's $field_type_converter closure.
		private function convertFieldTypeUsage(string $id, string $type, string $table, string $field, string $subset = ""): void
		{
			$rewrite = function (array $array) use ($id, $type) {
				foreach ($array as &$item) {
					if (($item["type"] ?? "") == $type) {
						$item["type"] = $id . "*" . $type;
					} elseif (($item["type"] ?? "") == "matrix") {
						if (empty($item["settings"])) {
							$item["settings"] = $item["options"] ?? [];
						}

						foreach ($item["settings"]["columns"] ?? [] as &$column) {
							if (($column["type"] ?? "") == $type) {
								$column["type"] = $id . "*" . $type;
							}
						}

						unset($column);
					}
				}

				unset($item);

				return $array;
			};

			if ($subset) {
				foreach (BigTreeJSONDB::getAll("modules") as $module) {
					$context = BigTreeJSONDB::getSubset("modules", $module["id"]);

					foreach ($context->getAll($subset) as $row) {
						if (!is_array($row[$field] ?? null)) {
							continue;
						}

						$context->update($subset, $row["id"], [$field => $rewrite($row[$field])]);
					}
				}

				return;
			}

			foreach (BigTreeJSONDB::getAll($table) as $row) {
				if (!is_array($row[$field] ?? null)) {
					continue;
				}

				BigTreeJSONDB::update($table, $row["id"], [$field => $rewrite($row[$field])]);
			}
		}

		// Move the selected loose files into extensions/{id}/, remapping their original
		// custom/* and templates/* locations to the extension layout (create.php tail).
		private function relocatePackageFiles(string $id, array $files): void
		{
			foreach ($files as $file) {
				$file = str_replace(SERVER_ROOT, "", (string)$file);

				if ($file === "" || substr($file, 0, 11) === "extensions/") {
					continue;
				}

				$dest = false;

				if (substr($file, 0, 18) === "custom/admin/ajax/") {
					$dest = "ajax/" . substr($file, 18);
				} elseif (substr($file, 0, 17) === "custom/admin/css/") {
					$dest = "css/" . substr($file, 17);
				} elseif (substr($file, 0, 16) === "custom/admin/js/") {
					$dest = "js/" . substr($file, 16);
				} elseif (substr($file, 0, 20) === "custom/admin/images/") {
					$dest = "images/" . substr($file, 20);
				} elseif (substr($file, 0, 21) === "custom/admin/modules/") {
					$dest = "modules/" . substr($file, 21);
				} elseif (substr($file, 0, 19) === "custom/inc/modules/") {
					$dest = "classes/" . substr($file, 19);
				} elseif (substr($file, 0, 10) === "templates/") {
					$dest = $file;
				} elseif (substr($file, 0, 5) === "site/") {
					if (strpos($file, "site/extensions/$id/") === 0) {
						BigTree::copyFile(SERVER_ROOT . $file, SERVER_ROOT . "extensions/$id/public/" . str_replace("site/extensions/$id/", "", $file));
					} else {
						BigTree::moveFile(SERVER_ROOT . $file, SITE_ROOT . "extensions/$id/" . substr($file, 5));
						BigTree::copyFile(SITE_ROOT . "extensions/$id/" . substr($file, 5), SERVER_ROOT . "extensions/$id/public/" . substr($file, 5));
					}

					continue;
				}

				if ($dest && file_exists(SERVER_ROOT . $file)) {
					BigTree::moveFile(SERVER_ROOT . $file, SERVER_ROOT . "extensions/$id/" . $dest);
				}
			}
		}

		// Read an installed extension's on-disk manifest (the source of truth for its
		// current revision); falls back to an empty array when missing.
		private function localManifest(string $id): array
		{
			$path = SERVER_ROOT . "extensions/" . str_replace(["/", "\\", ".."], "", $id) . "/manifest.json";

			if (!file_exists($path)) {
				return [];
			}

			return (array)@json_decode((string)file_get_contents($path), true);
		}

		private function present(array $ext) {

			return [
				"id" => $ext["id"] ?? "",
				"name" => $ext["name"] ?? ($ext["id"] ?? ""),
				"version" => $ext["version"] ?? "",
				"manifest" => $ext["manifest"] ?? [],
				"installed_at" => $ext["installed_at"] ?? null,
			];
		}
	}
