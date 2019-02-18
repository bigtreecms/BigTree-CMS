<?php
	if ($_POST["query"]) {
		$items = $admin->searchResources($_POST["query"]);
		$perm = "e";
		$bc = array(array("name" => "Clear Results","id" => ""));
	} else {
		$perm = $admin->getResourceFolderPermission($_POST["folder"]);
		$items = $admin->getContentsOfResourceFolder($_POST["folder"]);
		$bc = $admin->getResourceFolderBreadcrumb($_POST["folder"]);
	}
	
	if (!$_POST["query"] && $_POST["folder"] > 0) {
		$folder = $admin->getResourceFolder($_POST["folder"]);
?>
<button data-folder="<?=$folder["parent"]?>" class="file_list_button js-folder"><span class="icon_small icon_small_back"></span>Back</button>
<?php
	}
	
	foreach ($items["folders"] as $folder) {
?>
<button data-folder="<?=$folder["id"]?>" class="file_list_button js-folder"><span class="icon_small icon_small_folder"></span> <?=$folder["name"]?></button>
<?php
	}

	$minWidth = $_POST["minWidth"];
	$minHeight = $_POST["minHeight"];
?>
<div class="file_browser_images">
	<?php
		foreach ($items["resources"] as $resource) {
			if ($resource["is_image"]) {
				$resource["file"] = BigTreeCMS::replaceRelativeRoots($resource["file"]);
				$disabled = "";

				if (($minWidth && $minWidth !== "false" && $resource["width"] < $minWidth) ||
					($minHeight && $minHeight !== "false" && $resource["height"] < $minHeight) ||
					$access_level == "n"
				) {
					$disabled = " disabled";
				}

				// Filter out duplicate thumbnails
				$used = [];
				$thumbs = [];
				$resource_thumbs = json_decode($resource["thumbs"], true);

				foreach ($resource_thumbs as $prefix => $data) {
					$id = $data["width"]."x".$data["height"];

					if (!in_array($id, $used)) {
						$used[] = $id;
						$thumbs[$prefix] = $data;
					}
				}

				$data = htmlspecialchars(json_encode(array(
					"file" => $resource["file"],
					"thumbs" => $thumbs,
					"crops" => json_decode($resource["crops"])
				)));
	?>
	<button data-id="<?=$resource["id"]?>" data-image="<?=$data?>" data-name="<?=$resource["name"]?>" class="js-image<?=$disabled?> image">
		<img src="<?=BigTree::prefixFile($resource["file"], "list-preview/")?>" alt="" />
	</button>
	<?php
			}
		}

		// Make sure the breadcrumb is at most 5 pieces
		$cut_breadcrumb = array_slice($bc,-5,5);

		if (count($cut_breadcrumb) < count($bc)) {
			$cut_breadcrumb = array_merge(array(array("id" => 0,"name" => "&hellip;")),$cut_breadcrumb);
		}

		$crumb_contents = "";

		foreach ($cut_breadcrumb as $crumb) {
			$crumb_contents .= '<li><a href="#'.$crumb["id"].'" title="'.$crumb["name"].'">'.$crumb["name"].'</a></li>';
		}
	?>
</div>
<script>
	<?php if ($_POST["query"]) { ?>
	BigTreeFileManager.setTitleSuffix(": Search Results");
	<?php } else { ?>
	BigTreeFileManager.setTitleSuffix("");
	<?php } ?>
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"','\"',$crumb_contents)?>");
</script>