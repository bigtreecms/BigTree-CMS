<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}

	CSRF::verify();
	
	// Make sure an upload succeeded
	$error = $_FILES["file"]["error"];
	$errors = array();
	$warnings = array();
	
	if ($error == 1 || $error == 2) {
		$_SESSION["upload_error"] = "The file you uploaded is too large.  You may need to edit your php.ini to upload larger files.";
	} elseif ($error == 3) {
		$_SESSION["upload_error"] = "File upload failed.";
	}
	
	if ($error) {
		Router::redirect(DEVELOPER_ROOT."packages/install/");
	}
	
	// We've at least got the file now, unpack it and see what's going on.
	$file = $_FILES["file"]["tmp_name"];
	
	if (!$file) {
		$_SESSION["upload_error"] = "File upload failed.";
		Router::redirect(DEVELOPER_ROOT."extensions/install/");
	}
	
	// Clean up existing area
	$cache_root = SERVER_ROOT."cache/package/";
	FileSystem::deleteDirectory($cache_root);
	FileSystem::createDirectory($cache_root);

	// Unzip the extension
	include Router::getIncludePath("inc/lib/pclzip.php");
	$zip = new \PclZip($file);

	// See if this was downloaded off GitHub (will have a single root folder)
	$zip_root = Updater::zipRoot($zip);
	
	if ($zip_root) {
		$files = $zip->extract(PCLZIP_OPT_PATH, $cache_root, PCLZIP_OPT_REMOVE_PATH, $zip_root);
	} else {
		$files = $zip->extract(PCLZIP_OPT_PATH, $cache_root);
	}

	if (!$files) {
		FileSystem::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "The zip file uploaded was corrupt.";
		Router::redirect(DEVELOPER_ROOT."extensions/install/");
	}
	
	// Read the manifest
	$json = json_decode(file_get_contents($cache_root."manifest.json"),true);
	
	// Make sure it's legit -- we check the alphanumeric status of the ID because if it's invalid someone may be trying to put files in a bad directory
	if ($json["type"] != "extension" || !isset($json["id"]) || !isset($json["title"]) || !ctype_alnum(str_replace(array(".","_","-"),"",$json["id"]))) {
		FileSystem::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "The zip file uploaded does not appear to be a BigTree extension.";
		Router::redirect(DEVELOPER_ROOT."extensions/install/");
	}

	// Check if it's already installed
	if (SQL::exists("bigtree_extensions",$json["id"])) {
		FileSystem::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "An extension with the id of ".htmlspecialchars($json["id"])." is already installed.";
		Router::redirect(DEVELOPER_ROOT."extensions/install/");
	}
	
	// Check for table collisions
	foreach ((array)$json["components"]["tables"] as $table => $create_statement) {
		if (SQL::query("SHOW TABLES LIKE '$table'")->rows()) {
			$warnings[] = "A table named &ldquo;$table&rdquo; already exists &mdash; the table will be overwritten.";
		}
	}
	
	// Check file permissions and collisions
	foreach ((array)$json["files"] as $file) {
		if (!FileSystem::getDirectoryWritability(SERVER_ROOT.$file)) {
			$errors[] = "Cannot write to $file &mdash; please make the root directory or file writable.";
		} elseif (file_exists(SERVER_ROOT.$file)) {
			if (!is_writable(SERVER_ROOT.$file)) {
				$errors[] = "Cannot overwrite existing file: $file &mdash; please make the file writable or delete it.";
			} else {
				$warnings[] = "A file already exists at $file &mdash; the file will be overwritten.";
			}
		}
	}
?>
<div class="container">
	<div class="container_summary">
		<h2>
			<?=Text::htmlEncode($json["title"]." ".$json["version"])?>
			<small><?=Text::translate("by")?> <?=Text::htmlEncode($json["author"]["name"])?></small>
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
		<p><?=Text::translate("<strong>ERRORS OCCURRED!</strong> &mdash; Please correct all errors. You may not install this extension while errors persist.")?></p>
		<?php
			}
			
			if (!count($warnings) && !count($errors)) {
		?>
		<p><?=Text::translate("Extension is ready to be installed. No problems found.")?></p>
		<?php
			}
		?>
	</section>
	<?php if (!count($errors)) { ?>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>extensions/install/process/" class="button blue"><?=Text::translate("Install")?></a>
	</footer>
	<?php } ?>
</div>