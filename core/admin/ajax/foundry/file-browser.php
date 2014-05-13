<?
	// See if we have cloud support
	$cloud_options = array();
	if (!$_POST["cloud_disabled"] || $_POST["cloud_disabled"] == "false") {
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
	$parts = explode("/",$_POST["directory"]);
	$postdirectory = array();
	foreach ($parts as $part) {
		if ($part == "..") {
			unset($postdirectory[count($postdirectory)-1]);
		} elseif ($part) {
			$postdirectory[] = $part;
		}
	}
	if (count($postdirectory)) {
		$postdirectory = implode("/",$postdirectory)."/";
	} else {
		$postdirectory = "";
	}

	// Local storage is being browsed
	if ($location == "server") {
		$directory = SERVER_ROOT.$postdirectory;
		if ($postdirectory) {
			if (!$_POST["lockInSite"] || strlen($postdirectory) > 5)
				$subdirectories[] = "..";
		}
		$o = opendir($directory);
		while ($r = readdir($o)) {
			if ($r != "." && $r != "..") {
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
?>
<div class="directory"><?=str_replace(SERVER_ROOT,"/",$directory)?></div>
<div class="navigation_pane">
	<? if (count($cloud_options)) { ?>
	<ul class="cloud_options">
		<? foreach ($cloud_options as $option) { ?>
		<li><a data-type="location" href="<?=$option["class"]?>"<? if ($location == $option["class"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$option["class"]?>"></span><?=$option["title"]?></a></li>
		<? } ?>
	</ul>
	<? } ?>
	<ul>
		<?
			foreach ($subdirectories as $d) {
		?>
		<li><a href="<?=$d?>"><span class="icon_small icon_small_folder"></span><?=$d?></a></li>
		<?
			}
			foreach ($containers as $container) {
		?>
		<li><a data-type="container" href="<?=$container["name"]?>" title="<?=$container["name"]?>"><span class="icon_small icon_small_export"></span><?=$container["name"]?></a></li>
		<?
			}
		?>
	</ul>
</div>
<div class="browser_pane">
	<ul>
		<?
			foreach ($files as $file) {
				$parts = BigTree::pathInfo($file);
				$ext = strtolower($parts["extension"]);
		?>
		<li class="file<? if ($file == $_POST["file"]) { ?> selected<? } ?>"><span class="icon_small icon_small_file_default icon_small_file_<?=$ext?>"></span><p><?=$file?></p></li>
		<?
			}
		?>
	</ul>
	<input type="hidden" name="file" id="bigtree_foundry_file" value="<?=$_POST["file"]?>" />
	<input type="hidden" name="directory" value="<?=$postdirectory?>" id="bigtree_foundry_directory" />
	<input type="hidden" name="container" value="<?=$postcontainer?>" id="bigtree_foundry_container" />
	<input type="hidden" name="location" value="<?=$location?>" id="bigtree_foundry_location" />
	<input type="submit" value="Use Selected File" class="button blue" />
	<a href="#" class="button">Cancel</a>
</div>

<script>
	$("#bigtree_foundry_browser_window .navigation_pane a").click(function(ev) {
		type = $(this).attr("data-type");
		if (type == "location") {
			$("#bigtree_foundry_browser_form").load("<?=ADMIN_ROOT?>ajax/foundry/file-browser/", { location: $(this).attr("href") });
		} else if (type == "container") {
			$("#bigtree_foundry_browser_form").load("<?=ADMIN_ROOT?>ajax/foundry/file-browser/", { location: "<?=$location?>", container: $(this).attr("href") });	
		} else {
			directory = "<?=$postdirectory?>" + $(this).attr("href") + "/";
			$("#bigtree_foundry_browser_form").load("<?=ADMIN_ROOT?>ajax/foundry/file-browser/", { cloud_disabled: "<?=$_POST["cloud_disabled"]?>", location: "<?=$location?>", container: "<?=$postcontainer?>", directory: directory });
		}
		return false;
	});
	
	$("#bigtree_foundry_browser_window .browser_pane li").click(function() {
		$(".browser_pane li").removeClass("selected");
		$(this).addClass("selected");
		$("#bigtree_foundry_file").val($(this).find("p").html());
		return false;
	});
	
	$("#bigtree_foundry_browser_window a.button").click(function() {
		$(".bigtree_dialog_overlay, #bigtree_foundry_browser_window").remove();
		BigTree.zIndex -= 2;
		return false;
	});
</script>