<?php
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	// Get crop and thumb prefix info
	$dir = opendir(SITE_ROOT."files/temporary/".$admin->ID."/");

	$total_files = 0;

	while ($file = readdir($dir)) {
		if ($file == "." || $file == "..") {
			continue;
		}
		
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if ($extension == "jpg" || $extension == "jpeg" || $extension == "png" || $extension == "gif") {
			$file_name = SITE_ROOT."files/temporary/".$admin->ID."/".$file;
			$min_height = intval($preset["min_height"]);
			$min_width = intval($preset["min_width"]);
			
			list($width, $height, $type, $attr) = getimagesize($file_name);
			
			if ($min_height > $height || $min_width > $width) {
				$error = "Image uploaded (".htmlspecialchars($file_name).") did not meet the minimum size of ";
				
				if ($min_height && $min_width) {
					$error .= $min_width."x".$min_height." pixels.";
				} elseif ($min_height) {
					$error .= $min_height." pixels tall.";
				} elseif ($min_width) {
					$error .= $min_width." pixels wide.";
				}
				
				$bigtree["errors"][] = array("field" => "Image", "error" => $error);
				
				@unlink($file_name);
				
				continue;
			}
		
			$field = [
				"title" => $file,
				"file_input" => [
					"tmp_name" => $file_name,
					"name" => $file,
					"error" => 0
				],
				"settings" => [
					"directory" => "files/resources/",
					"preset" => "default"
				]
			];

			$output = $admin->processImageUpload($field);
			
			if ($output) {
				include BigTree::path("admin/modules/files/process/_resource-prefixes.php");
				$last_resource_id = $admin->createResource($bigtree["commands"][0], $output, $file, "image", $crop_prefixes, $thumb_prefixes);
				$total_files++;
			}
		}
	}

	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
		"return_link" =>($total_files > 1) ? ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/" :  ADMIN_ROOT."files/edit/file/$last_resource_id/",
		"crop_key" => $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"])
	];

	if (count($bigtree["errors"])) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p>Some images uploaded encountered <?=count($bigtree["errors"])?> error<?php if (count($bigtree["errors"]) != 1) { ?>s<?php } ?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column field">File</span>
				<span class="view_column error">Error</span>
			</header>
			<ul>
				<?php foreach ($bigtree["errors"] as $error) { ?>
				<li>
					<section class="view_column field"><?=$error["field"]?></section>
					<section class="view_column error"><?=$error["error"]?></section>
				</li>
				<?php } ?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>files/crop/<?=intval($bigtree["commands"][0])?>/" class="button blue">Continue</a>
	</footer>
</div>
<?php
	} else {
		BigTree::redirect(ADMIN_ROOT."files/crop/".intval($bigtree["commands"][0])."/");
	}
