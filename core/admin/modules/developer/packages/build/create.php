<?php
	namespace BigTree;
	
	/**
	 * @global array $author
	 * @global array $available_licenses
	 * @global array $callouts
	 * @global array $extension_settings
	 * @global array $feeds
	 * @global array $field_types
	 * @global array $files
	 * @global array $licenses
	 * @global array $module_groups
	 * @global array $modules
	 * @global array $settings
	 * @global array $tables
	 * @global array $templates
	 * @global string $compatibility
	 * @global string $description
	 * @global string $id
	 * @global string $keywords
	 * @global string $license
	 * @global string $license_name
	 * @global string $license_url
	 * @global string $title
	 * @global string $version
	 */
	
	// First we need to package the file so they can download it manually if they wish.
	if (!FileSystem::getDirectoryWritability(SERVER_ROOT."cache/package/")) {
		Auth::stop("Your cache/ and cache/package/ directories must be writable.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	FileSystem::createDirectory(SERVER_ROOT."cache/package/");
	
	// Fix keywords into an array
	$keywords = explode(",", $keywords);
	$keywords = array_map("trim", $keywords);
	
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
	
	foreach (array_filter((array) $module_groups) as $group_id) {
		$group = new ModuleGroup($group_id);
		$package["components"]["module_groups"][] = $group->Array;
	}
	
	foreach (array_filter((array) $callouts) as $callout_id) {
		$callout = new Callout($callout_id);
		$package["components"]["callouts"][] = $callout->Array;
	}
	
	foreach (array_filter((array) $feeds) as $feed_id) {
		$feed = new Feed($feed_id);
		$package["components"]["feeds"][] = $feed->Array;
	}
	
	foreach (array_filter((array) $settings) as $setting_id) {
		$setting = new Setting($setting_id);
		$package["components"]["settings"][] = $setting->Array;
	}
	
	foreach (array_filter((array) $field_types) as $type_id) {
		$type = new FieldType($type_id);
		$package["components"]["field_types"][] = $type->Array;
	}
	
	foreach (array_filter((array) $templates) as $template_id) {
		$template = new Template($template_id);
		$package["components"]["templates"][] = $template->Array;
	}
	
	foreach (array_filter((array) $modules) as $module_id) {
		$module = new Module($module_id);
		
		$module = $module->Array;
		$module["actions"] = ModuleAction::allByModule($module["id"], "position DESC, id ASC", true);
		$module["views"] = ModuleView::allByModule($module["id"], "title ASC", true);
		$module["forms"] = ModuleForm::allByModule($module["id"], "title ASC", true);
		$module["embed_forms"] = ModuleEmbedForm::allByModule($module["id"], "title ASC", true);
		$module["reports"] = ModuleReport::allByModule($module["id"], "title ASC", true);
		
		$package["components"]["modules"][] = $module;
	}
	
	foreach (array_filter((array) $tables) as $t) {
		list($table, $type) = explode("#", $t);
		$f = SQL::fetch("SHOW CREATE TABLE `$table`");
		$package["sql"][] = "DROP TABLE IF EXISTS `$table`";
		$package["sql"][] = str_replace(array("\r", "\n"), " ", end($f));
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
						$values[] = "'".SQL::escape(str_replace("\n", "\\n", $val))."'";
					}
				}
				$package["sql"][] = "INSERT INTO `$table` (".implode(",", $fields).") VALUES (".implode(",", $values).")";
			}
		}
		$package["components"]["tables"][] = $table;
	}
	$package["sql"][] = "SET foreign_key_checks = 1";
	
	foreach (array_filter((array) $files) as $file) {
		$file = Text::replaceServerRoot($file);
		FileSystem::copyFile(SERVER_ROOT.$file, SERVER_ROOT."cache/package/".$file);
		$package["files"][] = $file;
	}
	
	// Write the manifest file
	$json = JSON::encode($package);
	FileSystem::createFile(SERVER_ROOT."cache/package/manifest.json", $json);
	
	// Create the zip
	FileSystem::deleteFile(SERVER_ROOT."cache/package.zip");
	include Router::getIncludePath("inc/lib/pclzip.php");
	
	$zip = new \PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(FileSystem::getDirectoryContents(SERVER_ROOT."cache/package/"), PCLZIP_OPT_REMOVE_PATH, SERVER_ROOT."cache/package/");
	
	// Remove the package directory
	FileSystem::deleteDirectory(SERVER_ROOT."cache/package/");
	
	// Store it in the database for future updates
	if (SQL::exists("bigtree_extensions", $id)) {
		SQL::update("bigtree_extensions", $id, array(
			"name" => $title,
			"version" => $version,
			"manifest" => $json
		));
	} else {
		SQL::insert("bigtree_extensions", array(
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
		<p><?=Text::translate("Package created successfully.")?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>packages/build/download/" class="button blue"><?=Text::translate("Download")?></a>
	</footer>
</div>