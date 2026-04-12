<?php
	/**
	 * @global BigTreeAdmin $admin
	 * @global BigTreeCMS $cms
	 */

	$type = urldecode($_GET["type"] ?? "");

	$changes = $admin->getPublishableChanges();

	if ($type === "pages") {
		header("Content-Type: application/csv");
		header("Content-Disposition: attachment; filename=pending-changes-pages.csv");

		$csv = fopen("php://output", "w");
		fputcsv($csv, ["Author", "Page", "Type", "Updated"]);

		foreach ($changes as $change) {
			if ($change["table"] !== "bigtree_pages") {
				continue;
			}

			if (is_numeric($change["item_id"])) {
				$page = $cms->getPendingPage($change["item_id"]);

				if (!$page) {
					continue;
				}

				if (!$change["item_id"]) {
					$page["nav_title"] = "Home";
				}

				$change_type = "Edit";
			} else {
				$page = $cms->getPendingPage("p".$change["id"]);
				$change_type = "New";
			}

			fputcsv($csv, [
				$change["user"]["name"],
				html_entity_decode(strip_tags($page["nav_title"])),
				$change_type,
				$change["date"],
			]);
		}

		fclose($csv);
	} else {
		// Gather changes for this module
		$mod_changes = null;
		$mod_info = null;

		foreach ($changes as $change) {
			$mid = !empty($change["mod"]["id"]) ? $change["mod"]["id"] : null;

			if ($mid === $type) {
				if ($mod_info === null) {
					$mod_info = $change["mod"];
					$mod_info["table"] = $change["table"];
					$mod_changes = [];
				}

				$mod_changes[] = $change;
			}
		}

		if ($mod_info === null || $mod_changes === null) {
			die("Module not found.");
		}

		$safe_name = preg_replace('/[^a-z0-9\-]/', '-', strtolower($mod_info["name"]));
		header("Content-Type: application/csv");
		header("Content-Disposition: attachment; filename=pending-changes-".htmlspecialchars($safe_name).".csv");

		$view = BigTreeAutoModule::getViewForTable($mod_info["table"]);

		if ($view) {
			$view_data = BigTreeAutoModule::getViewData($view);
		} else {
			$view_data = false;
		}

		$csv = fopen("php://output", "w");

		// Build headers
		$headers = ["Author"];

		if ($view && is_array($view["fields"])) {
			foreach ($view["fields"] as $field) {
				$headers[] = $field["title"];
			}
		} else {
			$headers[] = "Item ID";
		}

		fputcsv($csv, $headers);

		foreach ($mod_changes as $change) {
			if ($change["item_id"]) {
				$item = $view_data ? ($view_data[$change["item_id"]] ?? null) : null;
				$item_id = $change["item_id"];
			} else {
				$item = $view_data ? ($view_data["p".$change["id"]] ?? null) : null;
				$item_id = "p".$change["id"];
			}

			if (!$item) {
				continue;
			}

			$row = [$change["user"]["name"]];

			if ($view && is_array($view["fields"])) {
				$x = 0;

				foreach ($view["fields"] as $field) {
					$x++;
					$row[] = html_entity_decode(strip_tags($item["column$x"]));
				}
			} else {
				$row[] = $item_id;
			}

			fputcsv($csv, $row);
		}

		fclose($csv);
	}

	die();
