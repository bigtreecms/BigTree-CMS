<?	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}

	$admin->verifyCSRFToken();
	
	// Make sure an upload succeeded
	$error = $_FILES["file"]["error"];
	if ($error == 1 || $error == 2) {
		$_SESSION["upload_error"] = "The file you uploaded is too large.  You may need to edit your php.ini to upload larger files.";
	} elseif ($error == 3) {
		$_SESSION["upload_error"] = "File upload failed.";
	}
	
	if ($error) {
		BigTree::redirect(DEVELOPER_ROOT."packages/install/");
	}
	
	// We've at least got the file now, unpack it and see what's going on.
	$file = $_FILES["file"]["tmp_name"];
	if (!$file) {
		$_SESSION["upload_error"] = "File upload failed.";
		BigTree::redirect(DEVELOPER_ROOT."extensions/install/");
	}
	
	// Clean up existing area
	$cache_root = SERVER_ROOT."cache/package/";
	BigTree::deleteDirectory($cache_root);
	BigTree::makeDirectory($cache_root);

	// Unzip the extension
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip($file);

	// See if this was downloaded off GitHub (will have a single root folder)
	$zip_root = BigTreeUpdater::zipRoot($zip);
	if ($zip_root) {
		$files = $zip->extract(PCLZIP_OPT_PATH,$cache_root,PCLZIP_OPT_REMOVE_PATH,$zip_root);
	} else {
		$files = $zip->extract(PCLZIP_OPT_PATH,$cache_root);
	}

	if (!$files) {
		BigTree::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "The file uploaded is either not a zip file or is corrupt.";
		BigTree::redirect(DEVELOPER_ROOT."extensions/install/");
	}
	
	// Read the manifest
	$json = json_decode(file_get_contents($cache_root."manifest.json"),true);
	// Make sure it's legit -- we check the alphanumeric status of the ID because if it's invalid someone may be trying to put files in a bad directory
	if ($json["type"] != "extension" || !isset($json["id"]) || !isset($json["title"]) || !ctype_alnum(str_replace(array(".","_","-"),"",$json["id"]))) {
		BigTree::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "The zip file uploaded does not appear to be a BigTree extension.";
		BigTree::redirect(DEVELOPER_ROOT."extensions/install/");
	}

	// Check if it's already installed
	if (sqlrows(sqlquery("SELECT * FROM bigtree_extensions WHERE id = '".sqlescape($json["id"])."'"))) {
		BigTree::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "An extension with the id of ".htmlspecialchars($json["id"])." is already installed.";
		BigTree::redirect(DEVELOPER_ROOT."extensions/install/");
	}
	
	// Check for table collisions
	foreach ((array)$json["components"]["tables"] as $table => $create_statement) {
		if (sqlrows(sqlquery("SHOW TABLES LIKE '$table'"))) {
			$warnings[] = "A table named &ldquo;$table&rdquo; already exists &mdash; the table will be overwritten.";
		}
	}
	
	// Check file permissions and collisions
	foreach ((array)$json["files"] as $file) {
		if (!BigTree::isDirectoryWritable(SERVER_ROOT.$file)) {
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
	<summary>
		<h2>
			<?=$json["title"]?> <?=$json["version"]?>
			<small>by <?=$json["author"]["name"]?></small>
		</h2>
	</summary>
	<section>
		<?
			if (count($warnings)) {
		?>
		<h3>Warnings</h3>
		<ul>
			<? foreach ($warnings as $w) { ?>
			<li><?=$w?></li>
			<? } ?>
		</ul>
		<?
			}
			
			if (count($errors)) {
		?>
		<h3>Errors</h3>
		<ul>
			<? foreach ($errors as $e) { ?>
			<li><?=$e?></li>
			<? } ?>
		</ul>
		<p><strong>ERRORS OCCURRED!</strong> &mdash; Please correct all errors.  You may not import this module while errors persist.</p>
		<?
			}
			
			if (!count($warnings) && !count($errors)) {
		?>
		<p>Extension is ready to be installed. No problems found.</p>
		<?
			}
		?>
	</section>
	<? if (!count($errors)) { ?>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>extensions/install/process/" class="button blue">Install</a>
	</footer>
	<? } ?>
</div>