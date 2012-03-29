<?
	$breadcrumb[] = array("title" => "Unpacked Package", "link" => "#");
	
	// Make sure an upload succeeded
	$error = $_FILES["file"]["error"];
	if ($error == 1 || $error == 2) {
		$_SESSION["upload_error"] = "The file you uploaded is too large.  You may need to edit your php.ini to upload larger files.";
	} elseif ($error == 3) {
		$_SESSION["upload_error"] = "File upload failed.";
	}
	
	if ($error) {
		header("Location: ".$developer_root."foundry/install/");
		die();
	}
	
	// We've at least got the file now, unpack it and see what's going on.
	$file = $_FILES["file"]["tmp_name"];
	if (!$file) {
		$_SESSION["upload_error"] = "File upload failed.";
		header("Location: ".$developer_root."foundry/install/");
		die();
	}
	
	if (!is_writable($server_root."cache/")) {
		die("<p>Your cache/ directory must be writable.</p>");
	}
	
	// Setup the cache root.
	$cache_root = $server_root."cache/unpack/";
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
		header("Location: ".$developer_root."foundry/install/");
		die();
	}
	
	exec("cd $cache_root; tar zxvf $local_copy");
	if (!file_exists($cache_root."index.btx")) {
		BigTree::deleteDirectory($cache_root);
		$_SESSION["upload_error"] = "The uploaded file is not a valid BigTree Package or is corrupt.";
		header("Location: ".$developer_root."foundry/install/");
		die();
	}
	
	$index = file_get_contents($cache_root."index.btx");
	$lines = explode("\n",$index);
	$package_name = $lines[0];
	$package_info = $lines[1];
	
	$errors = array();
	$warnings = array();
	next($lines);
	next($lines);
	foreach ($lines as $line) {
	    $parts = explode("::||BTX||::",$line);
	    $type = $parts[0];
	    $data = json_decode($parts[1],true);
	    
	    if ($type == "Template") {
	    	$r = sqlrows(sqlquery("SELECT * FROM bigtree_templates WHERE id = '".mysql_real_escape_string($data["id"])."'"));
	    	if ($r) {
	    		$warnings[] = "A template already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the template will be overwritten.";
	    	}
	    }
	    if ($type == "Callout") {
	    	$r = sqlrows(sqlquery("SELECT * FROM bigtree_callouts WHERE id = '".mysql_real_escape_string($data["id"])."'"));
	    	if ($r) {
	    		$warnings[] = "A sidelet already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the sidelet will be overwritten.";
	    	}
	    }
	    if ($type == "Setting") {
	    	$r = sqlrows(sqlquery("SELECT * FROM bigtree_settings WHERE id = '".mysql_real_escape_string($data["id"])."'"));
	    	if ($r) {
	    		$warnings[] = "A setting already exists with the id &ldquo;".$data["id"]."&rdquo; &mdash; the setting will be overwritten.";
	    	}
	    }
	    if ($type == "Feed") {
	    	$r = sqlrows(sqlquery("SELECT * FROM bigtree_feeds WHERE route = '".mysql_real_escape_string($data["route"])."'"));
	    	if ($r) {
	    		$warnings[] = "A feed already exists with the route &ldquo;".$data["route"]."&rdquo; &mdash; the feed will be overwritten.";
	    	}
	    }
	     if ($type == "FieldType") {
	    	$r = sqlrows(sqlquery("SELECT * FROM bigtree_field_types WHERE id = '".mysql_real_escape_string($data["id"])."'"));
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
	    	if (!BigTree::isWritable($server_root.$location)) {
	    		$errors[] = "Cannot write to $location &mdash; please make the root directory writable.";
	    	}
	    	if (file_exists($server_root.$location)) {
	    		$warnings[] = "A file already exists at $location &mdash; the file will be overwritten.";
	    	}
	    }
	}
	
?>
<h1><span class="package"></span>Unpacked Package</h1>
<div class="form_container">
	<header>
	    <h2>
	    	<?=$package_name?>
	    	<small><?=$package_info?></small>
	    </h2>
	</header>
	<section>
	    <?
	    	if (count($warnings)) {
	    ?>
	    <strong class="import_warnings">Warnings</strong>
	    <ul class="import_warnings">
	    	<? foreach ($warnings as $w) { ?>
	    	<li>&raquo; <?=$w?></li>
	    	<? } ?>
	    </ul>
	    <?
	    	}
	    	
	    	if (count($errors)) {
	    ?>
	    <strong class="import_errors">Errors</strong>
	    <ul class="import_errors">
	    	<? foreach ($errors as $e) { ?>
	    	<li>&raquo; <?=$e?></li>
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