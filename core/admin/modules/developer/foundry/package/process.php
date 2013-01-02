<?	
	// Function used for template directory inclusion:
	function _local_recurseFileDirectory($directory) {
		global $x,$index,$tname,$dir;
		$o = opendir($directory);
		while ($r = readdir($o)) {
			if ($r != "." && $r != "..") {
				if (is_dir($directory.$r)) {
					_local_recurseFileDirectory($directory.$r."/");
				} else {
					$x++;
					$index .= "File::||BTX||::$x.part.btx::||BTX||::".str_replace(SERVER_ROOT,"",$directory)."$r::||BTX||::Template\n";
					copy($directory.$r,$dir."$x.part.btx");
				}
			}
		}
	}
	
	// If someone accidentally added something twice, remove the duplicates.
	foreach ($_POST as $key => $val) {
		if (is_array($val)) {
			$_POST[$key] = array_unique($val);
		}
	}
	
	BigTree::globalizePOSTVars();
	
	// First we need to package the file so they can download it manually if they wish.
	if (!is_writable(SERVER_ROOT."cache/")) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your cache/ directory must be writable.</p>
	</section>
</div>
<?
		$admin->stop();
	}
	
	$index = $package_name."\n";
	$index .= "Packaged for BigTree ".BIGTREE_VERSION." by ".$created_by."\n";
	$index .= "Instructions::||BTX||::".json_encode(array("pre" => base64_encode($pre_instructions), "post" => base64_encode($post_instructions)))."\n";
	$index .= "InstallCode::||BTX||::".json_encode(base64_encode($install_code))."\n";
	
	if ($module) {
		$modules = array($admin->getModule($module));
	} elseif ($group) {
		$group_details = $admin->getModuleGroup($group);
		$modules = $admin->getModulesByGroup($group_details["id"]);
		$index .= "Group::||BTX||::".json_encode($group_details)."\n";
	}
	
	// Clear the cache area to build the package.
	$dir = SERVER_ROOT."cache/packager/";
	exec("rm -rf ".$dir);
	@unlink(SERVER_ROOT."cache/package.tar.gz");
	mkdir(SERVER_ROOT."cache/packager");
	$x = 0;

	$used_forms = array();
	$used_views = array();
	
	if (isset($modules) && is_array($modules)) {
		foreach ($modules as $item) {
			// Do stuff to dump databases here.
			$index .= "Module::||BTX||::".json_encode($item)."\n";
			// Find the actions for the module
			$actions = $admin->getModuleActions($item["id"]);
			foreach ($actions as $a) {
				// If there's an auto module, include it as well.
				if ($a["form"] && !in_array($a["form"],$used_forms)) {
					$form = BigTreeAutoModule::getForm($a["form"]);
					$index .= "ModuleForm::||BTX||::".json_encode($form)."\n";
					$used_forms[] = $a["form"];
				}
				if ($a["view"] && !in_array($a["view"],$used_views)) {
					$view = BigTreeAutoModule::getView($a["view"]);
					$index .= "ModuleView::||BTX||::".json_encode($view)."\n";
					$used_views[] = $a["view"];
				}
				// Draw Action after the form/view since we'll need to know the form/view ID to create the action.
				$index .= "Action::||BTX||::".json_encode($a)."\n";
			}
		}
	}
	
	// Get the templates we're passing in.
	if (isset($templates) && is_array($templates)) {
		foreach ($templates as $template) {
			$item = $cms->getTemplate($template);
			$index .= "Template::||BTX||::".json_encode($item)."\n";
			
			// If we're bringing over a module template, copy the whole darn folder.
			if ($item["routed"]) {
				_local_recurseFileDirectory(SERVER_ROOT."templates/routed/$template/"); 
			} else {
				$x++;
				copy(SERVER_ROOT."templates/basic/$template.php",$dir."$x.part.btx");
				$index .= "File::||BTX||::$x.part.btx::||BTX||::templates/basic/$template.php::||BTX||::Template\n";
			}
		}
	}
	
	// Get the callouts we're passing in.
	if (isset($callouts) && is_array($callouts)) {
		foreach ($callouts as $callout) {
			$item = $cms->getCallout($callout);
			$index .= "Callout::||BTX||::".json_encode($item)."\n";
			$x++;
			$index .= "File::||BTX||::$x.part.btx::||BTX||::templates/callouts/$callout.php\n";
			copy(SERVER_ROOT."templates/callouts/$callout.php",$dir."$x.part.btx");
		}
	}
	
	// Get the feeds
	if (isset($feeds) && is_array($feeds)) {
		foreach ($feeds as $feed) {
			$item = $cms->getFeed($feed);
			$index .= "Feed::||BTX||::".json_encode($item)."\n";
		}
	}
	
	// Get the settings
	if (isset($settings) && is_array($settings)) {
		foreach ($settings as $setting) {
			$item = $admin->getSetting($setting);
			$index .= "Setting::||BTX||::".json_encode($item)."\n";
		}
	}
	
	// Get the field types
	if (isset($field_types) && is_array($field_types)) {
		foreach ($field_types as $type) {
			$item = $admin->getFieldType($type);
			$index .= "FieldType::||BTX||::".json_encode($item)."\n";
		}
	}
	
	// Get the included tables now...
	if (isset($tables) && is_array($tables)) {
		// We need to rearrange the tables array so that ones that have foreign keys fall at the end.
		$rearranged_tables = array();
		$pending_tables = array();
		$table_info = array();
		foreach ($tables as $t) {
			list($table,$type) = explode("#",$t);
			$i = BigTree::describeTable($table);
			if (!count($i["foreign_keys"])) {
				$rearranged_tables[] = $t;
			} else {
				// See if the other tables are all bigtree_ ones or the key points to itself.
				$just_bigtree_keys = true;
				foreach ($i["foreign_keys"] as $key) {
					if (substr($key["other_table"],0,8) != "bigtree_" && $key["other_table"] != $table) {
						$just_bigtree_keys = false;
					}
				}
				if ($just_bigtree_keys) {
					$rearranged_tables[] = $t;
				} else {
					$table_info[$t] = $i;
					$pending_tables[] = $t;
				}
			}
		}
		// We're going to loop the number of times there are tables so we don't loop forever
		for ($i = 0; $i < count($pending_tables); $i++) {
			$t = $pending_tables[$i];
			$keys = $table_info[$t]["foreign_keys"];
			$ok = true;
			foreach ($keys as $key) {
				// If we haven't already found this foreign key table and it's not related to BigTree's core tables, we're not including it yet.
				if (!in_array($key["other_table"]."#data",$rearranged_tables) &&!in_array($key["other_table"]."#structure",$rearranged_tables) && substr($key["other_table"],0,8) != "bigtree_") {
					$ok = false;
				}
			}
			// If we've already got the table for this one's foreign keys, add it to the rearranged tables if we haven't already.
			if ($ok && !in_array($t,$rearranged_tables)) {
				$rearranged_tables[] = $t;
			}
		}
		
		// If we have less rearranged tables than pending tables we're missing a table dependancy.
		if (count($rearranged_tables) != count($tables)) {
			$failed_tables = array();
			foreach ($tables as $t) {
				if (!in_array($t,$rearranged_tables)) {
					list($table,$type) = explode("#",$t);
					$failed_tables[] = $table;
				}
			}
			$admin->stop('<div class="container"><section><div class="alert">
			<span></span><h3>Creation Failed</h3></div><p>The following tables have missing foreign key constraints: '.implode(", ",$failed_tables).'</p></section></div>');
		}
		
		foreach ($rearranged_tables as $t) {
			$x++;
			list($table,$type) = explode("#",$t);
			$f = sqlfetch(sqlquery("SHOW CREATE TABLE `$table`"));
			$create = "DROP TABLE IF EXISTS `$table`\n";
			$create .= str_replace(array("\r","\n")," ",end($f)).";\n";
			if ($type == "structure") {
				if (strpos($create,"AUTO_INCREMENT=") !== false) {
					$pos = strpos($create,"AUTO_INCREMENT=");
					$part1 = substr($create,0,$pos);
					$part2 = substr($create,strpos($create," ",$pos)+1);
					$create = $part1.$part2;
				}
			} else {
				$q = sqlquery("select * from `$table` order by id asc");
				while ($f = sqlfetch($q)) {
					$fields = array();
					$values = array();
					foreach ($f as $key => $val) {
						$fields[] = "`$key`";
						$values[] = '"'.sqlescape(str_replace(array("\r","\n")," ",$val)).'"';
					}
					$create .= "INSERT INTO `$table` (".implode(",",$fields).") VALUES (".implode(",",$values).");\n";
				}
			}
			file_put_contents($dir."$x.part.btx",$create);
			$index .= "SQL::||BTX||::$table::||BTX||::$x.part.btx\n";
		}
	}
	
	
	// Copy all the class files over...
	if (isset($class_files) && is_array($class_files)) {
		foreach ($class_files as $file) {
			$x++;
			// This is a module class, replace var $Module = "[num]";
			$module_for_file = false;
			foreach ($class_files as $mid => $cfn) {
				if ($cfn == $file)
					$module_for_file = $mid;
			}
			$index .= "ClassFile::||BTX||::$x.part.btx::||BTX||::$file::||BTX||::$module_for_file\n";
			copy(SERVER_ROOT.$file,$dir."$x.part.btx");	
		}
	}
	
	// Copy all the required files over...
	if (isset($required_files) && is_array($required_files)) {
		foreach ($required_files as $file) {
			$x++;
			$index .= "File::||BTX||::$x.part.btx::||BTX||::$file::||BTX||::Required\n";
			copy(SERVER_ROOT.$file,$dir."$x.part.btx");	
		}
	}
	
	// Copy all the other files over...
	if (isset($other_files) && is_array($other_files)) {
		foreach ($other_files as $file) {
			$x++;
			$index .= "File::||BTX||::$x.part.btx::||BTX||::$file::||BTX||::Other\n";
			copy(SERVER_ROOT.$file,$dir."$x.part.btx");	
		}
	}
	
	file_put_contents($dir."index.btx",$index);
	exec("cd $dir; tar -zcf ".SERVER_ROOT."cache/package.tar.gz *");
	
	// Create the saved copy of this creation.
	BigTree::globalizePOSTVars(array("sqlescape"));
	
	$package_file = BigTree::getAvailableFileName(SITE_ROOT."files/",$cms->urlify($package_name).".tgz");
	
	// Move the file into place.
	BigTree::moveFile(SERVER_ROOT."cache/package.tar.gz",SITE_ROOT."files/".$package_file);
?>
<div class="container">
	<section>
		<p>Package created successfully.  You may download it <a href="<?=WWW_ROOT?>files/<?=$package_file?>">by clicking here</a>.</p>
	</section>
</div>