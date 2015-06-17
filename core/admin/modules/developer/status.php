<?php
	//!BigTree Warnings
	$warnings = array();
	
	$writable_directories = array(
		"cache/",
		"custom/inc/modules/",
		"custom/admin/ajax/developer/field-options/",
		"custom/admin/form-field-types/draw/",
		"custom/admin/form-field-types/process/",
		"templates/routed/",
		"templates/basic/",
		"templates/callouts/",
		"site/files/"
	);
	
	foreach ($writable_directories as $directory) {
		if (!BigTree::isDirectoryWritable(SERVER_ROOT.$directory)) {
		    $warnings[] = array(
		    	"name" => "Directory Permissions Error",
		    	"description" => "Make ".SERVER_ROOT.$directory." writable.",
		    	"status" => "bad",
		    	"value" => ""
		    );
		}
	}
	
	// Go through every module form and look for uploads, make sure the directories exist and are writable.
	$forms = array_merge($admin->getModuleForms(),$admin->getModuleEmbedForms());
	foreach ($forms as $form) {
		foreach (array_filter((array)$form["fields"]) as $key => $data) {
			if ($data["directory"]) {
				if (!BigTree::isDirectoryWritable(SITE_ROOT.$data["directory"])) {
					$warnings[] = array(
						"name" => "Directory Permissions Error",
						"description" => "Make ".SITE_ROOT.$data["directory"]." writable.",
						"status" => "bad",
						"value" => ""
					);
				}
			}
		}
	}
	
	// Search all content for links to the admin.
	$bad = $admin->getPageAdminLinks();
	foreach ($bad as $f) {
		$warnings[] = array(
			"name" => "Bad Admin Links",
			"description" => 'Remove links to Admin on <a href="'.ADMIN_ROOT.'pages/edit/'.$f["id"].'/">'.$f["nav_title"].'</a>',
			"status" => "ok",
			"value" => ""
		);
	}
	
	if (!file_exists(SITE_ROOT."favicon.ico")) {
		$warnings[] = array(
			"name" => "Missing Favicon",
			"description" => "Create a favicon and place it in the /site/ root.",
			"status" => "ok",
			"value" => ""
		);
	}

	// See what the max file size upload is
	$upload_max_filesize = ini_get('upload_max_filesize');
	$post_max_size = ini_get('post_max_size');
	$max_file = (intval($upload_max_filesize) > intval($post_max_size)) ? intval($post_max_size) : intval($upload_max_filesize);	
	$max_check = "bad";
	if ($max_file >= 4) {
		$max_check = "ok";
	}
	if ($max_file >= 8) {
		$max_check = "good";
	}

	$server_parameters = array(
		array(
			"name" => "Magic Quotes",
			"description" => "&ldquo;magic_quotes_gpc = Off&rdquo; in php.ini",
			"status" => !get_magic_quotes_gpc() ? "good" : "bad",
			"value" => ""
		),
		array(
			"name" => "Magic Quotes Runtime Setting",
			"description" => "&ldquo;magic_quotes_gpc = Off&rdquo; at runtime",
			"status" => !get_magic_quotes_runtime() ? "good" : "bad",
			"value" => ""
		),
		array(
			"name" => "MySQL Support",
			"description" => 'MySQL or <a href="http://www.php.net/manual/en/mysqli.installation.php" target="_blank">MySQLi extension</a> is required',
			"status" => (extension_loaded('mysql') || extension_loaded("mysqli")) ? "good" : "bad",
			"value" => ""
		),
		array(
			"name" => "Allow File Uploads",
			"description" => "&ldquo;file_uploads = On&rdquo; in php.ini",
			"status" => ini_get('file_uploads') ? "good" : "bad",
			"value" => ""
		),
		array(
			"name" => "Allow 4MB Uploads",
			"description" => "&ldquo;upload_max_filesize&rdquo; and &ldquo;post_max_size&rdquo; > 4M &mdash; ideally 8M or higher in php.ini",
			"status" => $max_check,
			"value" => $max_file."M"
		),
		array(
			"name" => "Memory Limit",
			"description" => "&ldquo;memory_limit&rdquo; > 32M in php.ini",
			"status" => (intval(ini_get("memory_limit")) > 32) ? "good" : "bad",
			"value" => ini_get("memory_limit")
		),
		array(
			"name" => "Image Processing",
			"description" => '<a href="http://us3.php.net/manual/en/image.installation.php" target="_blank">GD extension</a> is required',
			"status" => extension_loaded('gd') ? "good" : "bad",
			"value" => ""
		),
		array(
			"name" => "cURL Support",
			"description" => '<a href="http://www.php.net/manual/en/curl.installation.php" target="_blank">cURL extension</a> is required',
			"status" => extension_loaded('curl') ? "good" : "bad",
			"value" => ""
		)
	);
?>
<div class="container">
	<section>
		<p>Critical errors appear in <span style="color: red;">red</span>, warnings appear in <span style="color: orange;">yellow</span>, and successes appear in <span style="color: green;">green</span>.</p>
	</section>
</div>
<div id="site_warnings_table"></div>
<div id="server_status_table"></div>
<script>
	<?php if (count($warnings)) { ?>
	// Site Warnings
	BigTreeTable({
		title: "Warnings",
		container: "#site_warnings_table",
		data: <?=json_encode($warnings)?>,
		actions: [],
		columns: {
			name: { title: "Problem", size: 0.3 },
			description: { title: "Recommended Action", size: 0.7 },
			status: { title: "Status", size: 90, center: true, source: '<span class="status {status}"></span>' }
		}
	});
	<?php } ?>

	// Server Status
	BigTreeTable({
		title: "Server Parameters",
		container: "#server_status_table",
		data: <?=json_encode($server_parameters)?>,
		actions: [],
		columns: {
			name: { title: "Server Parameter", size: 0.3 },
			description: { title: "Recommended Value", size: 0.7 },
			status: { title: "Status", size: 90, center: true, source: '<span class="status {status}">{value}</span>' }
		}
	});
</script>