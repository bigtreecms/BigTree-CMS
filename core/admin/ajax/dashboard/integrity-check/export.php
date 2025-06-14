<?php
	/**
	 * @global BigTreeAdmin $admin
	 */
	
	header("Content-Type: application/csv");
	header("Content-Disposition: attachment; filename=integrity-check.csv");
	
	$admin->requireLevel(1);
	$external = ($_GET["external"] == "true") ? true : false;
	$external_key = $external ? "external" : "internal";
	$session_key = "session.".$external_key;
	$session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", $session_key);
	$session_page_errors = [];
	$session_module_errors = [];

	$page_query = SQL::query("SELECT * FROM bigtree_caches
	                          WHERE `identifier` = 'org.bigtreecms.integritycheck'
	                          AND `key` LIKE 'errors.$external_key.pages.%'");

	while ($page_row = $page_query->fetch()) {
		$value = json_decode($page_row["value"], true);
		$id = str_replace("errors.$external_key.pages.", "", $page_row["key"]);

		if (!empty($value)) {
			$session_page_errors[$id] = $value;
		}
	}

	$module_query = SQL::query("SELECT * FROM bigtree_caches
	                            WHERE `identifier` = 'org.bigtreecms.integritycheck'
	                            AND `key` LIKE 'errors.$external_key.modules.%'");

	while ($module_row = $module_query->fetch()) {
		$value = json_decode($module_row["value"], true);
		$key = str_replace("errors.$external_key.modules.", "", $module_row["key"]);
		[$form_id, $item_id] = explode(".", $key);

		if (!empty($value)) {
			if (!isset($session_module_errors[$form_id])) {
				$session_module_errors[$form_id] = [];
			}

			$session_module_errors[$form_id][$item_id] = $value;
		}
	}

	$csv = fopen("php://output", "w");
	fputcsv($csv, ["Page/Module", "Page Title", "URL Type", "Broken URL", "Field", "Edit URL"]);
	
	foreach ($session_page_errors as $id => $page_errors) {
		$page = SQL::fetch("SELECT nav_title FROM bigtree_pages WHERE id = ?", $id);

		foreach ($page_errors as $title => $error_types) {
			foreach ($error_types as $type => $errors) {
				foreach ($errors as $error) {
					fputcsv($csv, [
						"Page",
						html_entity_decode($page["nav_title"]),
						($type == "img") ? "Image" : "Link",
						html_entity_decode($error),
						html_entity_decode($title),
						ADMIN_ROOT."pages/edit/".$id."/",
					]);
				}
			}
		}
	}
	
	foreach ($session["modules"] as $module) {
		if (!empty($session_module_errors[$module["id"]])) {
			$action = $admin->getModuleActionForForm($module);
			
			foreach ($session_module_errors[$module["id"]] as $entry_id => $module_errors) {
				foreach ($module_errors as $field  => $error_types) {
					foreach ($error_types as $type => $errors) {
						foreach ($errors as $error) {
							fputcsv($csv, [
								"Module",
								html_entity_decode($module["module_name"]),
								($type == "img") ? "Image" : "Link",
								html_entity_decode($error),
								html_entity_decode($field),
								ADMIN_ROOT.$module["module_route"]."/".$action["route"]."/".$entry_id."/",
							]);
						}
					}
				}
			}
		}
	}
	
	fclose($csv);
	
	die();
