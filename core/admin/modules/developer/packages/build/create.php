<?php
	namespace BigTree;
	
	// First we need to package the file so they can download it manually if they wish.
	if (!FileSystem::getDirectoryWritability(SERVER_ROOT."cache/package/")) {
		$admin->stop("Your cache/ and cache/package/ directories must be writable.",Router::getIncludePath("admin/layouts/_error.php"));
	}

	FileSystem::createDirectory(SERVER_ROOT."cache/package/");
	
	// Fix keywords into an array
	$keywords = explode(",",$keywords);
	foreach ($keywords as &$word) {
		$word = trim($word);
	}
	// Fix licenses into an array
	if ($license_name) {
		$license_array = array($license_name => $license_url);
	} elseif ($license) {
		$license_array = array($license => $available_licenses["Closed Source"][$license]);
	} else {
		$license_array = array();
		if (is_array($licenses)) {
			foreach ($licenses as $license) {
				$license_array[$license] = $available_licenses["Open Source"][$license];
			}
		}
	}
	
	// Setup JSON manifest
	$package = array(
		"type" => "package",
		"id" => $id,
		"version" => $version,
		"compatibility" => $compatibility,
		"title" => $title,
		"description" => $description,
		"keywords" => $keywords,
		"author" => $author,
		"licenses" => $license_array,
		"components" => array(
			"module_groups" => array(),
			"modules" => array(),
			"templates" => array(),
			"callouts" => array(),
			"settings" => array(),
			"feeds" => array(),
			"field_types" => array(),
			"tables" => array()
		),
		"sql" => array("SET foreign_key_checks = 0"),
		"files" => array()
	);

	$used_forms = array();
	$used_views = array();
	$used_reports = array();

	foreach ((array)$module_groups as $group) {
		$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
	}
	
	foreach ((array)$modules as $module) {
		$module = $admin->getModule($module);
		if ($module) {
			$module["actions"] = $admin->getModuleActions($module["id"]);
			$module["forms"] = $admin->getModuleForms($module["id"]);
			$module["views"] = $admin->getModuleViews($module["id"]);
			$module["reports"] = $admin->getModuleReports($module["id"]);
			$module["embed_forms"] = $admin->getModuleEmbedForms("title",$module["id"]);
			$package["components"]["modules"][] = $module;
		}
	}
	
	foreach ((array)$templates as $template) {
		$package["components"]["templates"][] = $cms->getTemplate($template);
	}
	
	foreach ((array)$callouts as $callout) {
		$package["components"]["callouts"][] = $admin->getCallout($callout);
	}
	
	foreach ((array)$feeds as $feed) {
		$package["components"]["feeds"][] = $cms->getFeed($feed);
	}
	
	foreach ((array)$settings as $setting) {
		$package["components"]["settings"][] = $admin->getSetting($setting);
	}
	
	foreach ((array)$field_types as $type) {
		$package["components"]["field_types"][] = $admin->getFieldType($type);
	}
	
	foreach ((array)$tables as $t) {
		$x++;
		list($table,$type) = explode("#",$t);
		$f = SQL::fetch("SHOW CREATE TABLE `$table`");
		$package["sql"][] = "DROP TABLE IF EXISTS `$table`";
		$package["sql"][] = str_replace(array("\r","\n")," ",end($f));
		if ($type != "structure") {
			$q = SQL::query("SELECT * FROM `$table`");
			while ($f = $q->fetch()) {
				$fields = array();
				$values = array();
				foreach ($f as $key => $val) {
					$fields[] = "`$key`";
					if ($val === null) {
						$values[] = "NULL";
					} else {
						$values[] = "'".SQL::escape(str_replace("\n","\\n",$val))."'";
					}
				}
				$package["sql"][] = "INSERT INTO `$table` (".implode(",",$fields).") VALUES (".implode(",",$values).")";
			}
		}
		$package["components"]["tables"][] = $table;
	}
	$package["sql"][] = "SET foreign_key_checks = 1";
	
	foreach ((array)$files as $file) {
		$file = BigTree::replaceServerRoot($file);
		FileSystem::copyFile(SERVER_ROOT.$file,SERVER_ROOT."cache/package/".$file);
		$package["files"][] = $file;
	}
	
	// Write the manifest file
	$json = JSON::encode($package);
	FileSystem::createFile(SERVER_ROOT."cache/package/manifest.json",$json);
	
	// Create the zip
	FileSystem::deleteFile(SERVER_ROOT."cache/package.zip");
	include Router::getIncludePath("inc/lib/pclzip.php");
	
	$zip = new \PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(FileSystem::getDirectoryContents(SERVER_ROOT."cache/package/"),PCLZIP_OPT_REMOVE_PATH,SERVER_ROOT."cache/package/");

	// Remove the package directory
	FileSystem::deleteDirectory(SERVER_ROOT."cache/package/");

	// Store it in the database for future updates
	if (SQL::exists("bigtree_extensions",$id)) {
		SQL::update("bigtree_extensions",$id,array(
			"name" => $title,
			"version" => $version,
			"manifest" => $json
		));
	} else {
		SQL::insert("bigtree_extensions",array(
			"id" => $id,
			"type" => "package",
			"name" => $title,
			"version" => $version,
			"manifest" => $json
		));
	}
?>
<div class="container">
	<section>
		<p>Package created successfully.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>packages/build/download/" class="button blue">Download</a>
	</footer>
</div>