<?
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
		    	"parameter" => "Directory Permissions Error",
		    	"rec" => "Make ".SERVER_ROOT.$directory." writable.",
		    	"status" => "bad"
		    );
		}
	}
	
	// Go through every module form and look for uploads, make sure the directories exist and are writable.
	$forms = array_merge($admin->getModuleForms(),$admin->getModuleEmbedForms());
	foreach ($forms as $form) {
		foreach ($form["fields"] as $key => $data) {
			if ($data["directory"]) {
				if (!BigTree::isDirectoryWritable(SITE_ROOT.$data["directory"])) {
					$warnings[] = array(
						"parameter" => "Directory Permissions Error",
						"rec" => "Make ".SITE_ROOT.$data["directory"]." writable.",
						"status" => "bad"
					);
				}
			}
		}
	}
	
	// Search all content for links to the admin.
	$bad = $admin->getPageAdminLinks();
	foreach ($bad as $f) {
		$warnings[] = array(
			"parameter" => "Bad Admin Links",
			"rec" => 'Remove links to Admin on <a href="'.ADMIN_ROOT.'pages/edit/'.$f["id"].'/">'.$f["nav_title"].'</a>',
			"status" => "ok"
		);
	}
	
	if (!file_exists(SITE_ROOT."favicon.ico")) {
		$warnings[] = array(
			"parameter" => "Missing Favicon",
			"rec" => "Create a favicon and place it in the /site/ root.",
			"status" => "ok"
		);
	}

	//!Server Parameters
	$mysql = (extension_loaded('mysql') || extension_loaded("mysqli")) ? "good" : "bad";
	$magic_quotes_gpc = !get_magic_quotes_gpc() ? "good" : "bad";
	$magic_quotes_runtime = !get_magic_quotes_runtime() ? "good" : "bad";
	$file_uploads = ini_get('file_uploads') ? "good" : "bad";
	$short_tags = ini_get('short_open_tag') ? "good" : "bad";
	$image_support = extension_loaded('gd') ? "good" : "bad";
	$curl_support = extension_loaded('curl') ? "good" : "bad";
	
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
	
	$mem_limit = ini_get("memory_limit");
	$memory_limit = (intval($mem_limit) > 32) ? "good" : "bad";
?>
<div class="container">
	<section>
		<p>Critical errors appear in <span style="color: red;">red</span>, warnings appear in <span style="color: orange;">yellow</span>, and successes appear in <span style="color: green;">green</span>.</p>
	</section>
</div>
<? if (count($warnings)) { ?>
<div class="table">
	<summary>
		<h2>Warnings</h2>
	</summary>
	<header>
		<span class="site_status_message">Warning</span>
		<span class="site_status_action">Recommended Action</span>
		<span class="site_status_status">Status</span>
	</header>
	<ul>
		<? foreach ($warnings as $w) { ?>
		<li>
			<section class="site_status_message"><?=$w["parameter"]?></section>
			<section class="site_status_action"><?=$w["rec"]?></section>
			<section class="site_status_status <?=$w["status"]?>"></section>
		</li>
		<? } ?>
	</ul>
</div>
<? } ?>
<div class="table">
	<summary>
		<h2>Server Parameters</h2>
	</summary>
	<header>
		<span class="site_status_message">Site Parameter</span>
		<span class="site_status_action">Recommended Value</span>
		<span class="site_status_status">Status</span>
	</header>
	<ul>
		<li>
			<section class="site_status_message">Magic Quotes</section>
			<section class="site_status_action">&ldquo;magic_quotes_gpc = Off&rdquo; in php.ini</section>
			<section class="site_status_status <?=$magic_quotes_gpc?>"></section>
		</li>
		<li>
			<section class="site_status_message">Magic Quotes Runtime Setting</section>
			<section class="site_status_action">&ldquo;magic_quotes_gpc = Off&rdquo; at runtime</section>
			<section class="site_status_status <?=$magic_quotes_runtime?>"></section>
		</li>	
		<li>
			<section class="site_status_message">Short Tags</section>
			<section class="site_status_action">&ldquo;short_open_tag = On&rdquo; in php.ini</section>
			<section class="site_status_status <?=$short_tags?>"></section>
		</li>
		<li>
			<section class="site_status_message">Allow File Uploads</section>
			<section class="site_status_action">&ldquo;file_uploads = On&rdquo; in php.ini</section>
			<section class="site_status_status <?=$file_uploads?>"></section>
		</li>
		<li>
			<section class="site_status_message">Allow 4MB Uploads</section>
			<section class="site_status_action">&ldquo;upload_max_filesize&rdquo; and &ldquo;post_max_size&rdquo; > 4M &mdash; ideally 8M or higher in php.ini</section>
			<section class="site_status_status <?=$max_check?>"><?=$max_file?>M</section>
		</li>
		<li>
			<section class="site_status_message">Memory Limit</section>
			<section class="site_status_action">&ldquo;memory_limit&rdquo; > 32M in php.ini</section>
			<section class="site_status_status <?=$memory_limit?>"><?=$mem_limit?></section>
		</li>
		<li>
			<section class="site_status_message">MySQL Support</section>
			<section class="site_status_action">MySQL or <a href="http://www.php.net/manual/en/mysqli.installation.php" target="_blank">MySQLi extension</a> is required</section>
			<section class="site_status_status <?=$mysql?>"></section>
		</li>
		<li>
			<section class="site_status_message">Image Processing</section>
			<section class="site_status_action"><a href="http://us3.php.net/manual/en/image.installation.php" target="_blank">GD extension</a> is required</section>
			<section class="site_status_status <?=$image_support?>"></section>
		</li>
		<li>
			<section class="site_status_message">cURL Support</section>
			<section class="site_status_action"><a href="http://www.php.net/manual/en/curl.installation.php" target="_blank">cURL extension</a> is required</section>
			<section class="site_status_status <?=$curl_support?>"></section>
		</li>
	</ul>
</div>