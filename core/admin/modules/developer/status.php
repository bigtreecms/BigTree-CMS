<?php
	namespace BigTree;
	
	$warnings = [];
	$writable_directories = [
		"cache/",
		"custom/inc/modules/",
		"custom/admin/field-types/",
		"templates/routed/",
		"templates/basic/",
		"templates/callouts/",
		"site/files/",
		"custom/json-db/"
	];
	
	foreach ($writable_directories as $directory) {
		if (!FileSystem::getDirectoryWritability(SERVER_ROOT.$directory)) {
		    $warnings[] = array(
		    	"name" => Text::translate("Directory Permissions Error"),
		    	"description" => Text::translate("Make :directory: writable.", false, array(":directory:" => SERVER_ROOT.$directory)),
		    	"status" => "bad"
		    );
		}
	}
	
	// Setup a recursive function to loop through fields
	$directory_warnings = [];
	
	$recurse_fields = function($fields) {
		global $directory_warnings, $recurse_fields, $warnings;
		
		foreach (array_filter((array)$fields) as $key => $data) {
			$settings = is_string($data["settings"]) ? array_filter((array)json_decode($data["settings"],true)) : $data["settings"];
			
			if ($data["type"] == "matrix") {
				$recurse_fields($settings["columns"]);
			} else {
				if ($settings["directory"]) {
					if (!FileSystem::getDirectoryWritability(SITE_ROOT.$settings["directory"]) && !in_array($settings["directory"], $directory_warnings)) {
						$directory_warnings[] = $settings["directory"];
						$warnings[] = array(
							"name" => Text::translate("Directory Permissions Error"),
							"description" => Text::translate("Make :directory: writable.", false, [":directory:" => SITE_ROOT.$settings["directory"]]),
							"status" => "bad"
						);
					}
				}
			}
		}
	};
	
	// Go through every module form and look for uploads, make sure the directories exist and are writable.
	$forms = ModuleForm::all("name ASC");
	$templates = Template::all("name ASC");
	$callouts = Callout::all("name ASC");
	
	foreach ($forms as $form) {
		$recurse_fields($form->Fields);
	}
	
	foreach ($templates as $template) {
		$recurse_fields($template->Fields);
	}
	
	foreach ($callouts as $callout) {
		$recurse_fields($callout->Fields);
	}
		
	// Search all content for links to the admin.
	$bad_links = Page::auditAdminLinks(true);
	
	foreach ($bad_links as $link) {
		$warnings[] = array(
			"name" => Text::translate("Bad Admin Links"),
			"description" => Text::translate('Remove links to Admin on <a href=":link:">:link_title:</a>', false,
											 [
											 	":link:" => ADMIN_ROOT.'pages/edit/'.$link["id"]."/",
												":link_title:" => $link["nav_title"]
											 ]),
			"status" => "ok"
		);
	}
	
	if (!file_exists(SITE_ROOT."favicon.ico")) {
		$warnings[] = array(
			"name" => Text::translate("Missing Favicon"),
			"description" => Text::translate("Create a favicon and place it in the /site/ root."),
			"status" => "ok"
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
	
	$server_parameters = [
		[
			"name" => "Magic Quotes",
			"description" => Text::translate('&ldquo;magic_quotes_gpc = Off&rdquo; in php.ini'),
			"status" => !get_magic_quotes_gpc() ? "good" : "bad",
			"value" => ""
		],
		[
			"name" => "Magic Quotes Runtime Setting",
			"description" => Text::translate("&ldquo;magic_quotes_gpc = Off&rdquo; at runtime"),
			"status" => !get_magic_quotes_runtime() ? "good" : "bad",
			"value" => ""
		],
		[
			"name" => "MySQL Support",
			"description" => Text::translate('MySQL or <a href=":mysqli_link:" target="_blank">MySQLi extension</a> is required', false, [":mysqli_link" => "http://www.php.net/manual/en/mysqli.installation.php"]),
			"status" => (extension_loaded('mysql') || extension_loaded("mysqli")) ? "good" : "bad",
			"value" => ""
		],
		[
			"name" => "Allow File Uploads",
			"description" => Text::translate("&ldquo;file_uploads = On&rdquo; in php.ini"),
			"status" => ini_get('file_uploads') ? "good" : "bad",
			"value" => ""
		],
		[
			"name" => "Allow 4MB Uploads",
			"description" => Text::translate("&ldquo;upload_max_filesize&rdquo; and &ldquo;post_max_size&rdquo; > 4M &mdash; ideally 8M or higher in php.ini"),
			"status" => $max_check,
			"value" => $max_file."M"
		],
		[
			"name" => "Memory Limit",
			"description" => Text::translate("&ldquo;memory_limit&rdquo; > 32M in php.ini"),
			"status" => (intval(ini_get("memory_limit")) > 32) ? "good" : "bad",
			"value" => ini_get("memory_limit")
		],
		[
			"name" => "Image Processing",
			"description" => Text::translate('<a href=":gd_link:" target="_blank">GD extension</a> is required', false, [":gd_link:" => "http://www.php.net/manual/en/image.installation.php"]),
			"status" => extension_loaded('gd') ? "good" : "bad",
			"value" => ""
		],
		[
			"name" => "cURL Support",
			"description" => Text::translate('<a href=":curl_link:" target="_blank">cURL extension</a> is required', false, [":curl_link:" => "http://www.php.net/manual/en/curl.installation.php"]),
			"status" => extension_loaded('curl') ? "good" : "bad",
			"value" => ""
		]
	];
?>
<div class="container">
	<section>
		<p><?=Text::translate('Critical errors appear in <span style="color: red;">red</span>, warnings appear in <span style="color: orange;">yellow</span>, and successes appear in <span style="color: green;">green</span>.')?></p>
	</section>
</div>
<div id="site_warnings_table"></div>
<div id="server_status_table"></div>
<script>
	<?php if (count($warnings)) { ?>
	// Site Warnings
	BigTreeTable({
		title: "<?=Text::translate("Warnings", true)?>",
		container: "#site_warnings_table",
		data: <?=json_encode($warnings)?>,
		actions: [],
		columns: {
			name: { title: "<?=Text::translate("Problem", true)?>", size: 0.3 },
			description: { title: "<?=Text::translate("Recommended Action", true)?>", size: 0.7 },
			status: { title: "<?=Text::translate("Status", true)?>", size: 90, center: true, source: '<span class="status {status}"></span>' }
		}
	});
	<?php } ?>

	// Server Status
	BigTreeTable({
		title: "<?=Text::translate("Server Parameters", true)?>",
		container: "#server_status_table",
		data: <?=json_encode($server_parameters)?>,
		actions: [],
		columns: {
			name: { title: "<?=Text::translate("Server Parameter", true)?>", size: 0.3 },
			description: { title: "<?=Text::translate("Recommended Value", true)?>", size: 0.7 },
			status: { title: "<?=Text::translate("Status", true)?>", size: 90, center: true, source: '<span class="status {status}">{value}</span>' }
		}
	});
</script>