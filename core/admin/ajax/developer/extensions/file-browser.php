<?php
	// See if we have cloud support
	$cloud_options = array();

	if (empty($_POST["cloud_disabled"]) || $_POST["cloud_disabled"] == "false") {
		$cloud = new BigTreeCloudStorage;
		
		if (!empty($cloud->Settings["amazon"]["active"])) {
			$cloud_options[] = array("class" => "amazon","title" => "Amazon S3");
		}

		if ($cloud->Connected) {
			$cloud_options[] = array("class" => "google","title" => "Google Cloud Storage");
		}

		if (!empty($cloud->Settings["rackspace"]["active"])) {
			$cloud_options[] = array("class" => "rackspace","title" => "Rackspace Cloud Files");
		}

		if (count($cloud_options)) {
			array_unshift($cloud_options,array("class" => "server","title" => "Local Server"));
		}
	}

	$location = !empty($_POST["location"]) ? $_POST["location"] : "server";
	$subdirectories = array();
	$files = array();
	$containers = array();
	
	// Get the post directory
	$postcontainer = !empty($_POST["container"]) ? $_POST["container"] : "";
	
	if ($_POST["location"] == "server") {
		$postdirectory = BigTree::cleanFile($_POST["directory"]);
	} else {
		$postdirectory = $_POST["directory"];
	}

	// Local storage is being browsed
	if ($location == "server") {
		$directory = SERVER_ROOT.$postdirectory;
		
		if ($postdirectory && $postdirectory != ltrim($_POST["base_directory"],"/")) {
			$subdirectories[] = "..";
		}

		$o = opendir($directory);

		while ($r = readdir($o)) {
			if ($r != "." && $r != ".." && $r != ".DS_Store") {
				if (is_dir($directory.$r)) {
					$subdirectories[] = $r;	
				} else {
					$files[] = $r;
				}
			}
		}
	} else {
		// If we're at ../ on the root of a container, go back to listing containers
		if ($_POST["directory"] == "../" && $postcontainer) {
			$postcontainer = false;
		}

		$cloud = new BigTreeCloudStorage($location);
		
		if (!$postcontainer) {
			$containers = $cloud->listContainers();
		} else {
			$subdirectories[] = "..";
			$container = $cloud->getContainer($_POST["container"]);
			
			if (!$postdirectory) {
				$folder = $container["tree"];
			} else {
				$folder = $cloud->getFolder($container,$postdirectory);
			}

			foreach ($folder["folders"] as $name => $contents) {
				$subdirectories[] = $name;
			}

			foreach ($folder["files"] as $file) {
				$files[] = $file["name"];
			}

			// Give it a nice directory name
			$directory = $postcontainer."/".$postdirectory;
		}
	}
	
	if (count($cloud_options)) {
		$bucket_pane_height = 338 - 1 - (26 * count($cloud_options));
	} else {
		$bucket_pane_height = 338;
	}
?>
<div class="directory"><?=htmlspecialchars(str_replace(SERVER_ROOT, "", $directory))?></div>
<div class="navigation_pane">
	<?php if (count($cloud_options)) { ?>
	<ul class="cloud_options">
		<?php foreach ($cloud_options as $option) { ?>
		<li><a data-type="location" href="<?=$option["class"]?>"<?php if ($location == $option["class"]) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$option["class"]?>"></span><?=$option["title"]?></a></li>
		<?php } ?>
	</ul>
	<?php } ?>
	<ul style="height: <?=$bucket_pane_height?>px;">
		<?php
			foreach ($subdirectories as $d) {
		?>
		<li><a href="<?=$d?>"><span class="icon_small icon_small_folder"></span><?=$d?></a></li>
		<?php
			}

			foreach ($containers as $container) {
		?>
		<li><a data-type="container" href="<?=$container["name"]?>" title="<?=$container["name"]?>"><span class="icon_small icon_small_export"></span><?=$container["name"]?></a></li>
		<?php
			}
		?>
	</ul>
</div>
<div class="browser_pane">
	<ul>
		<?php
			foreach ($files as $file) {
				$parts = BigTree::pathInfo($file);
				$ext = strtolower($parts["extension"]);
		?>
		<li class="file<?php if ($file == $_POST["file"]) { ?> selected<?php } ?>"><span class="icon_small icon_small_file_default icon_small_file_<?=$ext?>"></span><p><?=$file?></p></li>
		<?php
			}
		?>
	</ul>
	<input type="hidden" name="file" id="bigtree_foundry_file" value="<?=htmlspecialchars($_POST["file"])?>" />
	<input type="hidden" name="directory" value="<?=ltrim($postdirectory, "/")?>" id="bigtree_foundry_directory" />
	<input type="hidden" name="container" value="<?=$postcontainer?>" id="bigtree_foundry_container" />
	<input type="hidden" name="location" value="<?=$location?>" id="bigtree_foundry_location" />
	<input type="submit" value="Use Selected File" class="button blue" />
	<a href="#" class="button">Cancel</a>
</div>