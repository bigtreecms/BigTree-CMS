<?php
	$extension = $admin->getExtension($bigtree["commands"][0]);
	$manifest = $extension["manifest"];

	$_SESSION["bigtree_admin"]["developer"]["package"] = array(
		"id" => $manifest["id"],
		"version" => $manifest["version"],
		"compatibility" => $manifest["compatibility"],
		"title" => $manifest["title"],
		"description" => $manifest["description"],
		"keywords" => implode(", ",$manifest["keywords"]),
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

	foreach ($manifest["components"] as $k => $v) {
		if ($k == "tables") {
			$tables = array();
			
			foreach ($v as $table => $create_statement) {
				$_SESSION["bigtree_admin"]["developer"]["package"]["tables"][] = "$table#structure";
			}
		} else {
			foreach ($v as $item) {
				$_SESSION["bigtree_admin"]["developer"]["package"][$k][] = $item["id"];
			}
		}
	}
	
	foreach ($manifest["licenses"] as $l => $d) {
		if (isset($available_licenses["Open Source"][$l])) {
			$_SESSION["bigtree_admin"]["developer"]["package"]["licenses"][] = $l;
		} elseif (isset($available_licenses["Closed Source"][$l])) {
			$_SESSION["bigtree_admin"]["developer"]["package"]["license"] = $l;
		} else {
			$_SESSION["bigtree_admin"]["developer"]["package"]["license_name"] = $l;
			$_SESSION["bigtree_admin"]["developer"]["package"]["license_url"] = $d;
		}
	}

	BigTree::redirect(DEVELOPER_ROOT."extensions/build/details/");
