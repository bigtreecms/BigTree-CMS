<?
	// Make sure an upload succeeded
	$error = $_FILES["file"]["error"];
	if ($error == 1 || $error == 2) {
		$_SESSION["upload_error"] = "The file you uploaded is too large.  You may need to edit your php.ini to upload larger files.";
	} elseif ($error == 3) {
		$_SESSION["upload_error"] = "File upload failed.";
	}
	
	if ($error) {
		BigTree::redirect($developer_root."foundry/install/");
	}
	
	// We've at least got the file now, unpack it and see what's going on.
	$file = $_FILES["file"]["tmp_name"];
	if (!$file) {
		$_SESSION["upload_error"] = "File upload failed.";
		BigTree::redirect($developer_root."foundry/install/");
	}
	
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
	
	// Setup the cache root.
	$cache_root = SERVER_ROOT."cache/unpack/";
	if (!file_exists($cache_root)) {
		mkdir($cache_root);
	}
	$uniq_dir = uniqid();
	$cache_root .= $uniq_dir."/";
	mkdir($cache_root);
	chmod($cache_root,0777);

	// Move the uploaded file into the cache root.	
	$local_copy = BigTree::getAvailableFileName($cache_root,$_FILES["file"]["name"]);
	BigTree::moveFile($_FILES["file"]["tmp_name"],$cache_root.$local_copy);
	
	// Go through the file.
	if (!function_exists("exec")) {
		BigTree::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "PHP does not allow exec(). Packages can not be installed.";
		BigTree::redirect($developer_root."foundry/install/");
	}
	
	exec("cd $cache_root; tar zxvf $local_copy");
	if (!file_exists($cache_root."index.btx")) {
		BigTree::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "The uploaded file is not a valid BigTree Package or is corrupt.";
		BigTree::redirect($developer_root."foundry/install/");
	}
	
	$index = file_get_contents($cache_root."index.btx");
	$lines = explode("\n",$index);
	$package_name = $lines[0];
	$package_info = $lines[1];
	
	$instructions = array();
	$install_code = false;
	$errors = array();
	$warnings = array();
	next($lines);
	next($lines);
	foreach ($lines as $line) {
		$parts = explode("::||BTX||::",$line);
		$type = $parts[0];
		$data = json_decode($parts[1],true);
		
		if ($type == "Instructions") {
			$instructions = $data;
		}

		if ($type == "InstallCode") {
			$install_code = $data;
		}

		if ($type == "Template") {
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_templates WHERE id = '".sqlescape($data["id"])."'"));
			if ($r) {
				$warnings[] = "A template already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the template will be overwritten.";
			}
		}
		if ($type == "Callout") {
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_callouts WHERE id = '".sqlescape($data["id"])."'"));
			if ($r) {
				$warnings[] = "A sidelet already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the sidelet will be overwritten.";
			}
		}
		if ($type == "Setting") {
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_settings WHERE id = '".sqlescape($data["id"])."'"));
			if ($r) {
				$warnings[] = "A setting already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the setting will be overwritten.";
			}
		}
		if ($type == "Feed") {
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_feeds WHERE route = '".sqlescape($data["route"])."'"));
			if ($r) {
				$warnings[] = "A feed already exists with the route &ldquo;".$data["route"]."&rdquo; &mdash; the feed will be overwritten.";
			}
		}
		 if ($type == "FieldType") {
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_field_types WHERE id = '".sqlescape($data["id"])."'"));
			if ($r) {
				$warnings[] = "A field type already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the field type will be overwritten.";
			}
		}
		if ($type == "SQL") {
			$table = $parts[1];
			$r = sqlrows(sqlquery("SHOW TABLES LIKE '$table'"));
			if ($r) {
				$warnings[] = "A table named &ldquo;$table&rdquo; already exists &mdash; the table will be overwritten.";
			}
		}
		if ($type == "File") {
			$location = $parts[2];
			if (!BigTree::isDirectoryWritable(SERVER_ROOT.$location)) {
				$errors[] = "Cannot write to $location &mdash; please make the root directory writable.";
			}
			if (file_exists(SERVER_ROOT.$location)) {
				$warnings[] = "A file already exists at $location &mdash; the file will be overwritten.";
			}
		}
	}
	
?>
<div class="container">
	<header>
		<h2>
			<?=$package_name?>
			<small><?=$package_info?></small>
		</h2>
	</header>
	<section>
		<?
			if (count($instructions) && $instructions["pre"]) {
		?>
		<h3>Instructions</h3>
		<p><?=nl2br(htmlspecialchars(base64_decode($instructions["pre"])))?></p>
		<br />
		<hr />
		<?
			}

			if ($install_code) {
		?>
		<h3>Post Install Code</h3>
		<p>The following code will be run after the package is finished installing:</p>
		<pre><code class="language-php"><?=htmlspecialchars(ltrim(rtrim(base64_decode($install_code),"?>"),"<?"))?></code></pre>
		<br /><br />
		<hr />
		<?
			}

			if (count($warnings)) {
		?>
		<h3>Warnings</h3>
		<ul class="styled">
			<? foreach ($warnings as $w) { ?>
			<li><?=$w?></li>
			<? } ?>
		</ul>
		<?
			}
			
			if (count($errors)) {
		?>
		<h3>Errors</h3>
		<ul class="styled">
			<? foreach ($errors as $e) { ?>
			<li><?=$e?></li>
			<? } ?>
		</ul>
		<p><strong>ERRORS OCCURRED!</strong> &mdash; Please correct all errors.  You may not import this module while errors persist.</p>
		<?
			}
			
			if (!count($warnings) && !count($errors)) {
		?>
		<p>Package is ready to be installed. No problems found.</p>
		<?
			}
		?>
	</section>
	<? if (!count($errors)) { ?>
	<footer>
		<a href="../process/<?=$uniq_dir?>/" class="button blue">Install</a>
	</footer>
	<? } ?>
</div>