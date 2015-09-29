<?php
	// See if we've hit post_max_size
	if (!$_POST['_bigtree_post_check']) {
	    $_SESSION['bigtree_admin']['post_max_hit'] = true;
	    BigTree::redirect($_SERVER['HTTP_REFERER']);
	}

	// Make sure an upload succeeded
	$error = $_FILES['file']['error'];
	if ($error == 1 || $error == 2) {
	    $_SESSION['upload_error'] = 'The file you uploaded is too large.  You may need to edit your php.ini to upload larger files.';
	} elseif ($error == 3) {
	    $_SESSION['upload_error'] = 'File upload failed.';
	}

	if ($error) {
	    BigTree::redirect(DEVELOPER_ROOT.'packages/install/');
	}

	// We've at least got the file now, unpack it and see what's going on.
	$file = $_FILES['file']['tmp_name'];
	if (!$file) {
	    $_SESSION['upload_error'] = 'File upload failed.';
	    BigTree::redirect(DEVELOPER_ROOT.'packages/install/');
	}

	// Clean up existing area
	$cache_root = SERVER_ROOT.'cache/package/';
	BigTree::deleteDirectory($cache_root);
	BigTree::makeDirectory($cache_root);

	// Unzip the package
	include BigTree::path('inc/lib/pclzip.php');
	$zip = new PclZip($file);
	$files = $zip->extract(PCLZIP_OPT_PATH, $cache_root);
	if (!$files) {
	    BigTree::deleteDirectory($cache_root);
	    $_SESSION['upload_error'] = 'The zip file uploaded was corrupt.';
	    BigTree::redirect(DEVELOPER_ROOT.'packages/install/');
	}

	// Read the manifest
	$json = json_decode(file_get_contents($cache_root.'manifest.json'), true);
	// Make sure it's legit
	if ($json['type'] != 'package' || !isset($json['id']) || !isset($json['title'])) {
	    BigTree::deleteDirectory($cache_root);
	    $_SESSION['upload_error'] = 'The zip file uploaded does not appear to be a BigTree package.';
	    BigTree::redirect(DEVELOPER_ROOT.'packages/install/');
	}

	// Check for template collisions
	foreach ((array) $json['components']['templates'] as $template) {
	    if (sqlrows(sqlquery("SELECT * FROM bigtree_templates WHERE id = '".sqlescape($template['id'])."'"))) {
	        $warnings[] = 'A template already exists with the id &ldquo;'.$template['id'].'&rdquo; &mdash; the template will be overwritten.';
	    }
	}
	// Check for callout collisions
	foreach ((array) $json['components']['callouts'] as $callout) {
	    if (sqlrows(sqlquery("SELECT * FROM bigtree_callouts WHERE id = '".sqlescape($callout['id'])."'"))) {
	        $warnings[] = 'A callout already exists with the id &ldquo;'.$callout['id'].'&rdquo; &mdash; the callout will be overwritten.';
	    }
	}
	// Check for settings collisions
	foreach ((array) $json['components']['settings'] as $setting) {
	    if (sqlrows(sqlquery("SELECT * FROM bigtree_settings WHERE id = '".sqlescape($setting['id'])."'"))) {
	        $warnings[] = 'A setting already exists with the id &ldquo;'.$setting['id'].'&rdquo; &mdash; the setting will be overwritten.';
	    }
	}
	// Check for feed collisions
	foreach ((array) $json['components']['feeds'] as $feed) {
	    if (sqlrows(sqlquery("SELECT * FROM bigtree_feeds WHERE route = '".sqlescape($feed['route'])."'"))) {
	        $warnings[] = 'A feed already exists with the route &ldquo;'.$feed['route'].'&rdquo; &mdash; the feed will be overwritten.';
	    }
	}
	// Check for field type collisions
	foreach ((array) $json['components']['field_types'] as $type) {
	    if (sqlrows(sqlquery("SELECT * FROM bigtree_field_types WHERE id = '".sqlescape($type['id'])."'"))) {
	        $warnings[] = 'A field type already exists with the id &ldquo;'.$type['id'].'&rdquo; &mdash; the field type will be overwritten.';
	    }
	}
	// Check for table collisions
	foreach ((array) $json['sql'] as $command) {
	    if (substr($command, 0, 14) == 'CREATE TABLE `') {
	        $table = substr($command, 14);
	        $table = substr($table, 0, strpos($table, '`'));
	        if (sqlrows(sqlquery("SHOW TABLES LIKE '$table'"))) {
	            $warnings[] = "A table named &ldquo;$table&rdquo; already exists &mdash; the table will be overwritten.";
	        }
	    }
	}
	// Check file permissions and collisions
	foreach ((array) $json['files'] as $file) {
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
			<?=$json['title']?> <?=$json['version']?>
			<small>by <?=$json['author']['name']?></small>
		</h2>
	</summary>
	<section>
		<?php
			if (count($warnings)) {
			    ?>
		<h3>Warnings</h3>
		<ul>
			<?php foreach ($warnings as $w) {
    ?>
			<li><?=$w?></li>
			<?php 
}
			    ?>
		</ul>
		<?php

			}

			if (count($errors)) {
			    ?>
		<h3>Errors</h3>
		<ul>
			<?php foreach ($errors as $e) {
    ?>
			<li><?=$e?></li>
			<?php 
}
			    ?>
		</ul>
		<p><strong>ERRORS OCCURRED!</strong> &mdash; Please correct all errors.  You may not import this module while errors persist.</p>
		<?php

			}

			if (!count($warnings) && !count($errors)) {
			    ?>
		<p>Package is ready to be installed. No problems found.</p>
		<?php

			}
		?>
	</section>
	<?php if (!count($errors)) {
    ?>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>packages/install/process/" class="button blue">Install</a>
	</footer>
	<?php 
} ?>
</div>