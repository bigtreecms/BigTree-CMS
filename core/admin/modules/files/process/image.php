<?php
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	// Get crop and thumb prefix info
	$settings = $cms->getSetting("bigtree-internal-media-settings");
	$preset = $settings["presets"]["default"];
	$preset["directory"] = "files/resources/";

	$dir = opendir(SITE_ROOT."files/temporary/".$admin->ID."/");
	$total_files = 0;

	while ($file = readdir($dir)) {
		if ($file == "." || $file == "..") {
			continue;
		}
		
		$image = new BigTreeImage(SITE_ROOT."files/temporary/".$admin->ID."/".$file);
		
		if (empty($image->Error)) {
			$output = $admin->processImageUpload([
				"title" => $file,
				"file_input" => [
					"tmp_name" => $image->File,
					"name" => $file,
					"error" => 0
				],
				"settings" => [
					"directory" => "files/resources/",
					"preset" => "default"
				]
			]);
			
			if ($output) {
				include BigTree::path("admin/modules/files/process/_resource-prefixes.php");
				$last_resource_id = $admin->createResource($bigtree["commands"][0], $output, $file, "image", $crop_prefixes, $thumb_prefixes);
				$total_files++;
			}
		}
	}
	
	if ($total_files == 0) {
		$admin->growl("Files", "Upload failed.");
		BigTree::redirect(ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/");
	}

	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
		"return_link" => ($total_files > 1) ? ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/" :  ADMIN_ROOT."files/edit/file/$last_resource_id/",
		"crop_key" => $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"])
	];

	if (is_array($bigtree["errors"]) && count($bigtree["errors"])) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p>Some images uploaded caused <?=count($bigtree["errors"])?> error<?php if (count($bigtree["errors"]) != 1) { ?>s<?php } ?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column" style="width: calc(25% - 40px)">File</span>
				<span class="view_column" style="width: calc(75% - 40px)">Error</span>
			</header>
			<ul>
				<?php foreach ($bigtree["errors"] as $error) { ?>
				<li>
					<section class="view_column" style="width: calc(25% - 40px)"><?=$error["field"]?></section>
					<section class="view_column" style="width: calc(75% - 40px)"><?=$error["error"]?></section>
				</li>
				<?php } ?>
			</ul>
		</div>
	</section>
	<footer>
		<?php
			if (is_array($bigtree["crops"]) && count($bigtree["crops"])) {
		?>
		<a href="<?=ADMIN_ROOT?>files/crop/<?=intval($bigtree["commands"][0])?>/" class="button blue">Continue</a>
		<?php
			} else {
		?>
		<a href="<?=ADMIN_ROOT?>files/add/image/<?=intval($bigtree["commands"][0])?>/" class="button blue">Return</a>
		<?php
			}
		?>
	</footer>
</div>
<?php
	} else {
		if (is_array($bigtree["crops"]) && count($bigtree["crops"])) {
			BigTree::redirect(ADMIN_ROOT."files/crop/".intval($bigtree["commands"][0])."/");
		} else {
			BigTree::redirect($_SESSION["bigtree_admin"]["form_data"]["return_link"]);
		}
	}
