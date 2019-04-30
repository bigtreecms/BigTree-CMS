<?php
	namespace BigTree;
	
	$access_level = "n";
	
	if ($_POST["query"]) {
		$items = Resource::search($_POST["query"], "date DESC");
		$access_level = "e";
		$breadcrumb = [["name" => Text::translate("Clear Results"), "id" => ""]];
	} else {
		$folder = new ResourceFolder($_POST["folder"]);
		$access_level = $folder->UserAccessLevel;
		$items = $folder->Contents;
		$breadcrumb = $folder->Breadcrumb;
	}
	
	if (!empty($folder) && $folder->ID) {
?>
<a href="#<?=$folder->Parent?>" class="file folder back"><span class="icon_small icon_small_back"></span><?=Text::translate("Back")?></a>
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
				$resource["file"] = Link::detokenize($resource["file"]);
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

				$data = htmlspecialchars(json_encode([
					"file" => $resource["file"],
					"thumbs" => $thumbs,
					"crops" => json_decode($resource["crops"])
				]));
	?>
	<button data-id="<?=$resource["id"]?>" data-image="<?=$data?>" data-name="<?=$resource["name"]?>" class="js-image<?=$disabled?> image">
		<img src="<?=FileSystem::getPrefixedFile($resource["file"], "list-preview/")?>" alt="" />
	</button>
	<?php
			}
		}

		// Make sure the breadcrumb is at most 5 pieces
		$cut_breadcrumb = array_slice($breadcrumb,-5,5);

		if (count($cut_breadcrumb) < count($breadcrumb)) {
			$cut_breadcrumb = array_merge([["id" => 0, "name" => "&hellip;"]], $cut_breadcrumb);
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