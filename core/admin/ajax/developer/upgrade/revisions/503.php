<?php
	// BigTree 4.6 -- resource allocation backfill (batched for AJAX upgrades)

	$batch_size = 25;
	$reference_field_types = ["image-reference", "file-reference", "video-reference"];
	$content_field_types = ["html", "textarea", "link"];

	BigTreeAdmin::primeResourceFileCache();

	$valid_resources = array_flip(SQL::fetchAllSingle("SELECT id FROM bigtree_resources"));

	$insert_resource_allocations = function($table, $entry, $resource_ids) use ($valid_resources) {
		$entry = strval($entry);
		$resource_ids = array_unique(array_filter(array_map("intval", $resource_ids)));

		if (!$resource_ids) {
			return;
		}

		$existing = array_flip(SQL::fetchAllSingle(
			"SELECT resource FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ?",
			$table,
			$entry
		));

		foreach ($resource_ids as $resource) {
			if (!isset($valid_resources[$resource]) || isset($existing[$resource])) {
				continue;
			}

			SQL::insert("bigtree_resource_allocation", [
				"table" => $table,
				"entry" => $entry,
				"resource" => $resource,
				"updated_at" => "NOW()"
			]);

			$existing[$resource] = true;
		}
	};

	$get_field_keys = function($fields, $types) use (&$get_field_keys, $admin, &$callout_group_field_cache) {
		$keys = [];

		foreach ($fields as $field) {
			$type = $field["type"] ?? "";

			if (in_array($type, $types, true)) {
				$key = $field["column"] ?? $field["id"] ?? null;

				if ($key) {
					$keys[] = $key;
				}
			}

			if ($type === "callouts" && !empty($field["settings"]["callouts"])) {
				foreach ($field["settings"]["callouts"] as $callout) {
					$keys = array_merge($keys, $get_field_keys($callout["fields"] ?? [], $types));
				}
			}

			if ($type === "callouts" && !empty($field["settings"]["groups"])) {
				$group_key = implode(",", $field["settings"]["groups"]);

				if (!isset($callout_group_field_cache[$group_key])) {
					$callout_fields = [];

					foreach ($admin->getCalloutsInGroups($field["settings"]["groups"], false) as $callout) {
						$callout_fields = array_merge($callout_fields, $callout["resources"] ?? []);
					}

					$callout_group_field_cache[$group_key] = $get_field_keys($callout_fields, $types);
				}

				$keys = array_merge($keys, $callout_group_field_cache[$group_key]);
			}

			if ($type === "matrix" && !empty($field["settings"]["columns"])) {
				$keys = array_merge($keys, $get_field_keys($field["settings"]["columns"], $types));
			}
		}

		return array_values(array_unique(array_filter($keys)));
	};

	$callout_group_field_cache = [];

	$collect_resources_from_fields = function($data, $reference_keys) {
		return BigTreeAdmin::findResourcesInData($data, $reference_keys ?: null);
	};

	$decode_row_values = function($row) {
		$decoded = [];

		foreach ($row as $key => $val) {
			if (is_null($val)) {
				$decoded[$key] = null;
			} elseif (is_string($val) && is_array(json_decode($val, true))) {
				$decoded[$key] = BigTree::untranslateArray(json_decode($val, true));
			} else {
				$decoded[$key] = $val;
			}
		}

		return $decoded;
	};

	$collect_resources_from_row = function($row, $reference_keys) use ($collect_resources_from_fields, $decode_row_values) {
		return $collect_resources_from_fields($decode_row_values($row), $reference_keys);
	};

	$build_segments = function() use ($admin) {
		$segments = [
			[
				"type" => "pages",
				"label" => "pages",
				"count" => (int) SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages")
			],
			[
				"type" => "pending_pages",
				"label" => "pending page drafts",
				"count" => (int) SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages'")
			],
			[
				"type" => "open_graph",
				"label" => "open graph entries",
				"count" => (int) SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_open_graph WHERE image IS NOT NULL AND image != ''")
			],
			[
				"type" => "settings",
				"label" => "settings",
				"count" => count(BigTreeJSONDB::getAll("settings"))
			]
		];

		$seen_tables = [];

		foreach ($admin->getModuleForms() as $form) {
			$table = $form["table"] ?? "";

			if (!$table || !SQL::tableExists($table) || isset($seen_tables[$table])) {
				continue;
			}

			$seen_tables[$table] = true;

			$segments[] = [
				"type" => "module_table",
				"label" => "module table $table",
				"table" => $table,
				"fields" => $form["fields"] ?? [],
				"count" => (int) SQL::fetchSingle("SELECT COUNT(*) FROM `$table`")
			];
		}

		$segments[] = [
			"type" => "pending_modules",
			"label" => "pending module drafts",
			"count" => (int) SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes WHERE `table` != 'bigtree_pages'")
		];

		return $segments;
	};

	$get_total_pages = function($segments) use ($batch_size) {
		$total_pages = 0;

		foreach ($segments as $segment) {
			if ($segment["count"] > 0) {
				$total_pages += (int) ceil($segment["count"] / $batch_size);
			}
		}

		return $total_pages;
	};

	$resolve_page = function($page, $segments) use ($batch_size) {
		$page_index = 1;

		foreach ($segments as $segment) {
			if ($segment["count"] <= 0) {
				continue;
			}

			$segment_pages = (int) ceil($segment["count"] / $batch_size);

			if ($page <= $page_index + $segment_pages - 1) {
				return [
					"segment" => $segment,
					"batch" => $page - $page_index + 1,
					"segment_pages" => $segment_pages
				];
			}

			$page_index += $segment_pages;
		}

		return null;
	};

	$template_reference_keys = [];
	$module_reference_keys = [];

	foreach (BigTreeJSONDB::getAll("templates") as $template) {
		$template_reference_keys[$template["id"]] = $get_field_keys($template["resources"] ?? [], $reference_field_types);
	}

	foreach ($admin->getModuleForms() as $form) {
		$table = $form["table"] ?? "";

		if (!$table) {
			continue;
		}

		$keys = $get_field_keys($form["fields"] ?? [], $reference_field_types);

		if (isset($module_reference_keys[$table])) {
			$module_reference_keys[$table] = array_values(array_unique(array_merge($module_reference_keys[$table], $keys)));
		} else {
			$module_reference_keys[$table] = $keys;
		}
	}

	$process_batch = function($work) use (
		$batch_size,
		$template_reference_keys,
		$module_reference_keys,
		$reference_field_types,
		$content_field_types,
		$insert_resource_allocations,
		$collect_resources_from_fields,
		$collect_resources_from_row,
		$admin
	) {
		$segment = $work["segment"];
		$batch = $work["batch"];
		$start = ($batch - 1) * $batch_size;

		if ($segment["type"] === "pages") {
			$pages = SQL::fetchAll("SELECT id, template, resources, `external` FROM bigtree_pages ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($pages as $page) {
				$resources_data = json_decode($page["resources"], true) ?: [];
				$reference_keys = $template_reference_keys[$page["template"]] ?? [];
				$data = [
					"resources" => $resources_data,
					"external" => $page["external"]
				];

				$insert_resource_allocations("bigtree_pages", $page["id"], $collect_resources_from_fields($data, $reference_keys));
			}
		} elseif ($segment["type"] === "pending_pages") {
			$pending_pages = SQL::fetchAll("SELECT id, changes FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($pending_pages as $change) {
				$changes = json_decode($change["changes"], true) ?: [];
				$reference_keys = $template_reference_keys[$changes["template"] ?? ""] ?? [];

				$insert_resource_allocations("bigtree_pages", "p".$change["id"], $collect_resources_from_fields($changes, $reference_keys));
			}
		} elseif ($segment["type"] === "open_graph") {
			$open_graph_entries = SQL::fetchAll("SELECT `table`, entry, image FROM bigtree_open_graph WHERE image IS NOT NULL AND image != '' ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($open_graph_entries as $entry) {
				$insert_resource_allocations($entry["table"], $entry["entry"], BigTreeAdmin::findResourcesInData($entry["image"], null));
			}
		} elseif ($segment["type"] === "settings") {
			$settings = BigTreeJSONDB::getAll("settings");
			$settings_batch = array_slice($settings, $start, $batch_size);

			foreach ($settings_batch as $setting) {
				$stored_setting = $admin->getSetting($setting["id"]);
				$value = $stored_setting["value"] ?? null;

				if ($value === null || $value === "") {
					continue;
				}

				$setting_type = $setting["type"] ?? "";
				$reference_keys = null;

				if (in_array($setting_type, $reference_field_types, true)) {
					$reference_keys = ["value"];
				}

				$insert_resource_allocations("bigtree_settings", $setting["id"], $collect_resources_from_fields(["value" => $value], $reference_keys));
			}
		} elseif ($segment["type"] === "module_table") {
			$table = $segment["table"];
			$reference_keys = $module_reference_keys[$table] ?? [];
			$rows = SQL::fetchAll("SELECT * FROM `$table` ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($rows as $row) {
				$insert_resource_allocations($table, $row["id"], $collect_resources_from_row($row, $reference_keys));
			}
		} elseif ($segment["type"] === "pending_modules") {
			$pending_changes = SQL::fetchAll("SELECT id, `table`, changes FROM bigtree_pending_changes WHERE `table` != 'bigtree_pages' ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($pending_changes as $change) {
				$changes = json_decode($change["changes"], true) ?: [];
				$reference_keys = $module_reference_keys[$change["table"]] ?? [];

				$insert_resource_allocations($change["table"], "p".$change["id"], $collect_resources_from_fields($changes, $reference_keys));
			}
		}
	};

	if (empty($_GET["page"])) {
		$segments = $build_segments();
		$total_pages = $get_total_pages($segments);

		if ($total_pages === 0) {
			$admin->updateInternalSettingValue("bigtree-internal-revision", 503);

			echo BigTree::json([
				"complete" => true,
				"response" => "Upgrading to BigTree 4.6 (resource allocation backfill)"
			]);
		} else {
			echo BigTree::json([
				"complete" => false,
				"response" => "Backfilling resource allocations...",
				"pages" => $total_pages
			]);
		}

		die();
	}

	$page = intval($_GET["page"]);
	$total_pages = intval($_GET["total_pages"]);
	$segments = $build_segments();
	$work = $resolve_page($page, $segments);

	if ($work) {
		$process_batch($work);

		$segment = $work["segment"];
		$response = "Backfilling resource allocations (".$segment["label"]."): batch ".$work["batch"]." of ".$work["segment_pages"]." complete.";
	} else {
		$response = "Backfilling resource allocations: finalizing...";
	}

	if ($page < $total_pages) {
		echo BigTree::json([
			"complete" => false,
			"response" => $response
		]);
	} else {
		$admin->updateInternalSettingValue("bigtree-internal-revision", 503);

		echo BigTree::json([
			"complete" => true,
			"response" => "Upgrading to BigTree 4.6 (resource allocation backfill)"
		]);
	}