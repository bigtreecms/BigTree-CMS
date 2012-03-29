<?
	$breadcrumb = array(
		array("link" => "developer/", "title" => "Developer"),
		array("link" => "developer/status/", "title" => "Site Status")
	);

	//!BigTree Warnings
	
	// Check for Google Analytics
	$content = file_get_contents($www_root);
	if (strpos($content,"google-analytics.com/ga.js") === false) {
		$warnings[] = array(
			"parameter" => "Google Analytics",
			"rec" => "We recommend you install Google Analytics for tracking data.",
			"status" => "ok"
		);
	}
	
	$writable_directors = array(
		"cache/",
		"custom/inc/modules/",
		"templates/routed/",
		"templates/basic/",
		"templates/callouts/",
		"site/files/"
	);
	
	// Go through every module form and look for uploads, make sure the directories exist and are writable.
	$forms = $admin->getModuleForms();
	foreach ($forms as $form) {
		foreach ($form["fields"] as $key => $data) {
			if ($data["directory"]) {
				if (!BigTree::isDirectoryWritable($site_root.$data["directory"])) {
					$warnings[] = array(
						"parameter" => "Directory Permissions Error",
						"rec" => "Make ".$site_root.$data["directory"]." writable.",
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
			"rec" => 'Remove links to Admin on <a href="'.$admin_root.'pages/edit/'.$f["id"].'/">'.$f["nav_title"].'</a>',
			"status" => "ok"
		);
	}
	
	if (!file_exists($site_root."favicon.ico")) {
		$warnings[] = array(
			"parameter" => "Missing Favicon",
			"rec" => "Create a favicon and place it in the /site/ root.",
			"status" => "ok"
		);
	}

	//!Server Parameters
	$json = extension_loaded('json') ? "good" : "ok";
	$mysql = extension_loaded('mysql') ? "good" : "bad";
	$magic_quotes_gpc = !get_magic_quotes_gpc() ? "good" : "bad";
	$magic_quotes_runtime = !get_magic_quotes_runtime() ? "good" : "bad";
	$file_uploads = ini_get('file_uploads') ? "good" : "bad";
	$short_tags = ini_get('short_open_tag') ? "good" : "bad";
	$gd = extension_loaded('gd') ? "good" : "bad";
	if ($gd == "good") {
		$image_support = extension_loaded('imagick') ? "good" : "ok";
	} else {
		$image_support = extension_loaded("imagick") ? "good" : "bad";
	}
	
	$ftp = function_exists("ftp_connect") ? "good" : "ok";
	
	$upload_max_filesize = ini_get('upload_max_filesize');
	$post_max_size = ini_get('post_max_size');
	$max_file = (intval($upload_max_filesize) > intval($post_max_size)) ? intval($post_max_size) : intval($upload_max_filesize);
	
	$max_check = "bad";
	if ($max_file >= 4)
		$max_check = "ok";
	if ($max_file >= 8)
		$max_check = "good";
	
	$mem_limit = ini_get("memory_limit");
	$memory_limit = (intval($mem_limit) > 32) ? "good" : "bad";
	
	$apache_modules = apache_get_modules();
	$mod_rewrite = in_array('mod_rewrite', $apache_modules) ? "good" : "bad";
	
	$fopen_url = ini_get("allow_url_fopen") ? "good" : "ok";
?>
<h1>Site Status</h1>
<p>Critical errors appear in <span style="color: red;">red</span>, warnings appear in <span style="color: orange;">yellow</span>, and successes appear in <span style="color: green;">green</span>.</p>

<div class="table">
	<summary>
		<h2 class="no_icon">Warnings</h2>
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
<div class="table">
	<summary>
		<h2 class="no_icon">Server Parameters</h2>
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
			<section class="site_status_message">JSON Support</section>
			<section class="site_status_action">JSON module is required.</section>
			<section class="site_status_status <?=$json?>"></section>
		</li>
		<li>
			<section class="site_status_message">FTP Support</section>
			<section class="site_status_action">FTP module is required for Dev/Live Sync</section>
			<section class="site_status_status <?=$json?>"></section>
		</li>
		<li>
			<section class="site_status_message">MySQL Support</section>
			<section class="site_status_action">MySQL Module is required</section>
			<section class="site_status_status <?=$mysql?>"></section>
		</li>
		<li>
			<section class="site_status_message">Image Processing</section>
			<section class="site_status_action">Either ImageMagick (recommended - green) or GD (yellow) is required</section>
			<section class="site_status_status <?=$image_support?>"></section>
		</li>
		<li>
			<section class="site_status_message">URL Rewrite Support</section>
			<section class="site_status_action">mod_rewrite for Apache is required</section>
			<section class="site_status_status <?=$mod_rewrite?>"></section>
		</li>
		<li>
			<section class="site_status_message">External URL Opening</section>
			<section class="site_status_action">allow_url_fopen in php.ini should be on for advanced features to work</section>
			<section class="site_status_status <?=$fopen_url?>"></section>
		</li>
	</ul>
</div>