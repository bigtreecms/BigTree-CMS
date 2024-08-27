<?php
	/**
	 * @global BigTreeAdmin $admin
	 */
	
	header("Content-Type: application/csv");
	header("Content-Disposition: attachment; filename=integrity-check.csv");
	
	$admin->requireLevel(1);
	$external = ($_GET["external"] == "true") ? true : false;
	$session_key = "session.".($external ? "external" : "internal");
	$session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", $session_key);
	
	$csv = fopen("php://output", "w");
	fputcsv($csv, ["Page/Module", "Page Title", "URL Type", "Broken URL", "Field", "Edit URL"]);
	
	if (!empty($session["errors"]["pages"])) {
		foreach ($session["errors"]["pages"] as $id => $page_errors) {
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
	}
	
	foreach ($session["modules"] as $module) {
		if (!empty($session["errors"][$module["id"]])) {
			$action = $admin->getModuleActionForForm($module);
			
			foreach ($session["errors"][$module["id"]] as $entry_id => $module_errors) {
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
