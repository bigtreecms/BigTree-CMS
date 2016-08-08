<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$extension = new Extension($bigtree["commands"][0]);
	$manifest = $extension->Manifest;
	
	$_SESSION["bigtree_admin"]["developer"]["package"] = array(
		"id" => $manifest["id"],
		"version" => $manifest["version"],
		"compatibility" => $manifest["compatibility"],
		"title" => $manifest["title"],
		"description" => $manifest["description"],
		"keywords" => implode(", ", $manifest["keywords"]),
		"author" => $manifest["author"],
		"files" => array(),
		"modules" => array(),
		"templates" => array(),
		"callouts" => array(),
		"settings" => array(),
		"feeds" => array(),
		"field_types" => array(),
		"tables" => array()
	);
	
	foreach ($manifest["components"] as $key => $data) {
		if ($key == "tables") {
			foreach ($data as $table => $create_statement) {
				$_SESSION["bigtree_admin"]["developer"]["package"]["tables"][] = "$table#structure";
			}
		} else {
			foreach ($data as $item) {
				$_SESSION["bigtree_admin"]["developer"]["package"][$key][] = $item["id"];
			}
		}
	}
	
	foreach ($manifest["licenses"] as $license => $data) {
		if (isset($available_licenses["Open Source"][$license])) {
			$_SESSION["bigtree_admin"]["developer"]["package"]["licenses"][] = $license;
		} elseif (isset($available_licenses["Closed Source"][$license])) {
			$_SESSION["bigtree_admin"]["developer"]["package"]["license"] = $license;
		} else {
			$_SESSION["bigtree_admin"]["developer"]["package"]["license_name"] = $license;
			$_SESSION["bigtree_admin"]["developer"]["package"]["license_url"] = $data;
		}
	}
	
	Router::redirect(DEVELOPER_ROOT."extensions/build/details/");
	