<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	// Make sure an upload succeeded
	$error = $_FILES["file"]["error"];
	if ($error == 1 || $error == 2) {
		$_SESSION["upload_error"] = Text::translate("The file you uploaded is too large.  You may need to edit your php.ini to upload larger files.");
	} elseif ($error == 3) {
		$_SESSION["upload_error"] = Text::translate("File upload failed.");
	}
	
	if ($error) {
		Router::redirect(DEVELOPER_ROOT."packages/install/");
	}
	
	// We've at least got the file now, unpack it and see what's going on.
	$file = $_FILES["file"]["tmp_name"];
	if (!$file) {
		$_SESSION["upload_error"] = Text::translate("File upload failed.");
		Router::redirect(DEVELOPER_ROOT."packages/install/");
	}
	
	// Clean up existing area
	$cache_root = SERVER_ROOT."cache/package/";
	FileSystem::deleteDirectory($cache_root);
	FileSystem::createDirectory($cache_root);

	// Unzip the package
	include Router::getIncludePath("inc/lib/pclzip.php");
	$zip = new \PclZip($file);
	$files = $zip->extract(PCLZIP_OPT_PATH,$cache_root);
	if (!$files) {
		FileSystem::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = Text::translate("The zip file uploaded was corrupt.");
		Router::redirect(DEVELOPER_ROOT."packages/install/");
	}
	
	// Read the manifest
	$json = json_decode(file_get_contents($cache_root."manifest.json"),true);
	// Make sure it's legit
	if ($json["type"] != "package" || !isset($json["id"]) || !isset($json["title"])) {
		FileSystem::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = Text::translate("The zip file uploaded does not appear to be a BigTree package.");
		Router::redirect(DEVELOPER_ROOT."packages/install/");
	}
	
	// Check for template collisions
	foreach ((array)$json["components"]["templates"] as $template) {
		if (SQL::exists("bigtree_templates",$template["id"])) {
			$warnings[] = Text::translate("A template already exists with the id &ldquo;:template_id:&rdquo; &mdash; the template will be overwritten.", false, array(":template_id:" => $template["id"]));
		}
	}
	// Check for callout collisions
	foreach ((array)$json["components"]["callouts"] as $callout) {
		if (SQL::exists("bigtree_callouts",$callout["id"])) {
			$warnings[] = Text::translate("A callout already exists with the id &ldquo;:callout_id:&rdquo; &mdash; the callout will be overwritten.", false, array(":callout_id:" => $callout["id"]));
		}
	}
	// Check for settings collisions
	foreach ((array)$json["components"]["settings"] as $setting) {
		if (SQL::exists("bigtree_settings",$setting["id"])) {
			$warnings[] = Text::translate("A setting already exists with the id &ldquo;:setting_id:&rdquo; &mdash; the setting will be overwritten.", false, array(":setting_id:" => $setting["id"]));
		}
	}
	// Check for feed collisions
	foreach ((array)$json["components"]["feeds"] as $feed) {
		if (SQL::exists("bigtree_feeds",$feed["route"])) {
			$warnings[] = Text::translate("A feed already exists with the route &ldquo;:feed_route:&rdquo; &mdash; the feed will be overwritten.", false, array(":feed_route:" => $feed["route"]));
		}
	}
	// Check for field type collisions
	foreach ((array)$json["components"]["field_types"] as $type) {
		if (SQL::exists("bigtree_field_types",$type["id"])) {
			$warnings[] = Text::translate("A field type already exists with the id &ldquo;:field_type_id:&rdquo; &mdash; the field type will be overwritten.", false, array(":field_type_id:" => $type["id"]));
		}
	}
	// Check for table collisions
	foreach ((array)$json["sql"] as $command) {
		if (substr($command,0,14) == "CREATE TABLE `") {
			$table = substr($command,14);
			$table = substr($table,0,strpos($table,"`"));
			if (SQL::query("SHOW TABLES LIKE '$table'")->rows()) {
				$warnings[] = Text::translate("A table named &ldquo;:table:&rdquo; already exists &mdash; the table will be overwritten.", false, array(":table:" => $table));
			}
		}
	}
	// Check file permissions and collisions
	foreach ((array)$json["files"] as $file) {
		if (!FileSystem::getDirectoryWritability(SERVER_ROOT.$file)) {
			$errors[] = Text::translate("Cannot write to :file_path: &mdash; please make the root directory or file writable.", false, array(":file_path:" => $file));
		} elseif (file_exists(SERVER_ROOT.$file)) {
			if (!is_writable(SERVER_ROOT.$file)) {
				$errors[] = Text::translate("Cannot overwrite existing file: :file_path: &mdash; please make the file writable or delete it.", false, array(":file_path:" => $file));
			} else {
				$warnings[] = Text::translate("A file already exists at :file_path: &mdash; the file will be overwritten.", false, array(":file_path:" => $file));
			}
		}
	}
?>
<div class="container">
	<div class="container_summary">
		<h2>
			<?=$json["title"]?> <?=$json["version"]?>
			<small><?=Text::translate("by")?> <?=$json["author"]["name"]?></small>
		</h2>
	</div>
	<section>
		<?php
			if (count($warnings)) {
		?>
		<h3><?=Text::translate("Warnings")?></h3>
		<ul>
			<?php foreach ($warnings as $w) { ?>
			<li><?=$w?></li>
			<?php } ?>
		</ul>
		<?php
			}
			
			if (count($errors)) {
		?>
		<h3><?=Text::translate("Errors")?></h3>
		<ul>
			<?php foreach ($errors as $e) { ?>
			<li><?=$e?></li>
			<?php } ?>
		</ul>
		<p><?=Text::translate("<strong>ERRORS OCCURRED!</strong> &mdash; Please correct all errors. You may not import this module while errors persist.")?></p>
		<?php
			}
			
			if (!count($warnings) && !count($errors)) {
		?>
		<p><?=Text::translate("Package is ready to be installed. No problems found.")?></p>
		<?php
			}
		?>
	</section>
	<?php if (!count($errors)) { ?>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>packages/install/process/" class="button blue"><?=Text::translate("Install")?></a>
	</footer>
	<?php } ?>
</div>