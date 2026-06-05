<?php
	// BigTree 4.6 -- resource allocation backfill (batched for AJAX upgrades)

	$batch_size = 25;
	$reference_field_types = ["image-reference", "file-reference", "video-reference"];
	$content_field_types = ["html", "textarea", "link"];

	$valid_resources = array_flip(SQL::fetchAllSingle("SELECT id FROM bigtree_resources"));

	$insert_resource_allocations = function($table, $entry, $resource_ids) use ($valid_resources) {
		$entry = strval($entry);

		foreach (array_unique(array_filter(array_map("intval", $resource_ids))) as $resource) {
			if (!isset($valid_resources[$resource])) {
				continue;
			}

			if (!SQL::exists("bigtree_resource_allocation", ["table" => $table, "entry" => $entry, "resource" => $resource])) {
				SQL::insert("bigtree_resource_allocation", [
					"table" => $table,
					"entry" => $entry,
					"resource" => $resource,
					"updated_at" => "NOW()"
				]);
			}
		}
	};

	$get_field_keys = function($fields, $types) use (&$get_field_keys, $admin) {
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
				$callouts = $admin->getCalloutsInGroups($field["settings"]["groups"], false);

				foreach ($callouts as $callout) {
					$keys = array_merge($keys, $get_field_keys($callout["resources"] ?? [], $types));
				}
			}

			if ($type === "matrix" && !empty($field["settings"]["columns"])) {
				$keys = array_merge($keys, $get_field_keys($field["settings"]["columns"], $types));
			}
		}

		return array_values(array_unique(array_filter($keys)));
	};

	$collect_resources_from_fields = function($data, $fields) use ($get_field_keys, $reference_field_types, $content_field_types) {
		$reference_keys = $get_field_keys($fields, $reference_field_types);
		$content_keys = $get_field_keys($fields, $content_field_types);
		$resources = BigTreeAdmin::findResourcesInData($data, $reference_keys);

		$scan_content_fields = function($value) use (&$scan_content_fields, $content_keys) {
			$found = [];

			if (is_array($value)) {
				foreach ($value as $key => $piece) {
					if (in_array($key, $content_keys, true) && $piece !== null && $piece !== "") {
						$found = array_merge($found, BigTreeAdmin::findResourcesInData($piece, null));
					}

					$found = array_merge($found, $scan_content_fields($piece));
				}
			}

			return $found;
		};

		return array_merge($resources, $scan_content_fields($data), BigTreeAdmin::findResourcesInData($data, null));
	};

	$collect_resources_from_row = function($row, $fields) use ($collect_resources_from_fields) {
		$resources = $collect_resources_from_fields($row, $fields);

		foreach ($row as $value) {
			if (is_string($value) && $value !== "" && ($value[0] === "[" || $value[0] === "{")) {
				$decoded = json_decode($value, true);

				if (is_array($decoded)) {
					$resources = array_merge($resources, $collect_resources_from_fields($decoded, $fields));
				}
			}
		}

		return $resources;
	};

	$build_segments = function() {
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
				"count" => (int) SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_open_graph")
			],
			[
				"type" => "settings",
				"label" => "settings",
				"count" => count(BigTreeJSONDB::getAll("settings"))
			]
		];

		foreach (BigTreeJSONDB::getAll("module-forms") as $form) {
			$table = $form["table"] ?? "";

			if (!$table || !SQL::tableExists($table)) {
				continue;
			}

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

	// Cached per request for batch processors
	$template_fields = [];

	foreach (BigTreeJSONDB::getAll("templates") as $template) {
		$template_fields[$template["id"]] = $template["resources"] ?? [];
	}

	$module_fields_by_table = [];

	foreach (BigTreeJSONDB::getAll("module-forms") as $form) {
		$table = $form["table"] ?? "";

		if ($table) {
			$module_fields_by_table[$table] = $form["fields"] ?? [];
		}
	}

	$process_batch = function($work) use (
		$batch_size,
		$template_fields,
		$module_fields_by_table,
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
			$pages = SQL::fetchAll("SELECT id, template, resources, external FROM bigtree_pages ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($pages as $page) {
				$resources_data = json_decode($page["resources"], true) ?: [];
				$fields = $template_fields[$page["template"]] ?? [];
				$data = [
					"resources" => $resources_data,
					"external" => $page["external"]
				];

				$resource_ids = array_merge(
					$collect_resources_from_fields($data, $fields),
					$collect_resources_from_fields($resources_data, $fields)
				);

				$insert_resource_allocations("bigtree_pages", $page["id"], $resource_ids);
			}
		} elseif ($segment["type"] === "pending_pages") {
			$pending_pages = SQL::fetchAll("SELECT id, changes FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($pending_pages as $change) {
				$changes = json_decode($change["changes"], true) ?: [];
				$fields = $template_fields[$changes["template"] ?? ""] ?? [];

				$insert_resource_allocations("bigtree_pages", "p".$change["id"], $collect_resources_from_fields($changes, $fields));
			}
		} elseif ($segment["type"] === "open_graph") {
			$open_graph_entries = SQL::fetchAll("SELECT `table`, entry, image FROM bigtree_open_graph ORDER BY id ASC LIMIT $start, $batch_size");

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
				$resource_ids = [];

				if (in_array($setting_type, $reference_field_types, true)) {
					$resource_ids = BigTreeAdmin::findResourcesInData(["value" => $value], ["value"]);
				}

				if (in_array($setting_type, $content_field_types, true)) {
					$resource_ids = array_merge($resource_ids, BigTreeAdmin::findResourcesInData($value, null));
				}

				if (!$resource_ids) {
					$resource_ids = BigTreeAdmin::findResourcesInData($value, null);
				}

				$insert_resource_allocations("bigtree_settings", $setting["id"], $resource_ids);
			}
		} elseif ($segment["type"] === "module_table") {
			$table = $segment["table"];
			$fields = $segment["fields"];
			$rows = SQL::fetchAll("SELECT * FROM `$table` ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($rows as $row) {
				$insert_resource_allocations($table, $row["id"], $collect_resources_from_row($row, $fields));
			}
		} elseif ($segment["type"] === "pending_modules") {
			$pending_changes = SQL::fetchAll("SELECT id, `table`, changes FROM bigtree_pending_changes WHERE `table` != 'bigtree_pages' ORDER BY id ASC LIMIT $start, $batch_size");

			foreach ($pending_changes as $change) {
				$changes = json_decode($change["changes"], true) ?: [];
				$fields = $module_fields_by_table[$change["table"]] ?? [];

				$insert_resource_allocations($change["table"], "p".$change["id"], $collect_resources_from_fields($changes, $fields));
			}
		}
	};

	$segments = $build_segments();
	$total_pages = $get_total_pages($segments);

	if (empty($_GET["page"])) {
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