<?php
	namespace BigTree;
	
	// Fallback for failed folder
	$access_level = "n";
	$breadcrumb = [];
	$items = ["folders" => [], "resources" => []];
	
	if ($_POST["query"]) {
		$items = Resource::search($_POST["query"]);
		$access_level = "e";
		$breadcrumb = [["name" => "Clear Results", "id" => ""]];
	} else {
		if (ResourceFolder::exists($_POST["folder"])) {
			$folder = new ResourceFolder($_POST["folder"]);
			$access_level = $folder->UserAccessLevel;
			$breadcrumb = $folder->Breadcrumb;
			$items = $folder->Contents;
			
			if ($folder->ID) {
?>
<button data-folder="<?=$folder->Parent?>" class="file_list_button js-folder"><span class="icon_small icon_small_back"></span>Back</button>
<?php
			}
		}
	}

	foreach ($items["folders"] as $folder) {
?>
<button data-folder="<?=$folder["id"]?>" class="file_list_button js-folder"><span class="icon_small icon_small_folder"></span> <?=$folder["name"]?></button>
<?php
	}

	foreach ($items["resources"] as $resource) {
		if ($resource["type"] != "video") {
?>
<button class="file_list_button js-file<?php if ($access_level == "n") { ?> disabled<?php } ?>" data-file="<?=$resource["file"]?>" data-id="<?=$resource["id"]?>" data-name="<?=$resource["name"]?>" data-href="<?=BigTreeCMS::replaceRelativeRoots($resource["file"])?>">
	<span class="icon_small icon_small_file_default icon_small_file_<?= $resource["type"] ?>"></span> <?=$resource["name"]?>
</button>
<?php
		}
	}

	// Make sure the breadcrumb is at most 5 pieces
	$cut_breadcrumb = array_slice($breadcrumb, -5, 5);

	if (count($cut_breadcrumb) < count($breadcrumb)) {
		$cut_breadcrumb = array_merge([["id" => 0, "name" => "&hellip;"]], $cut_breadcrumb);
	}

	$crumb_contents = "";

	foreach ($cut_breadcrumb as $crumb) {
		$crumb_contents .= '<li><a href="#'.$crumb["id"].'" title="'.$crumb["name"].'">'.$crumb["name"].'</a></li>';
	}
?>
<script>
	<?php if ($_POST["query"]) { ?>
	BigTreeFileManager.setTitleSuffix(": Search Results");
	<?php } else { ?>
	BigTreeFileManager.setTitleSuffix("");
	<?php } ?>
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"','\"',$crumb_contents)?>");
</script>