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

	foreach ($items["resources"] as $resource) {
		if ($resource["type"] != "video") {
?>
<button class="file_list_button js-file<?php if ($perm == "n") { ?> disabled<?php } ?>" data-file="<?=$resource["file"]?>" data-id="<?=$resource["id"]?>" data-name="<?=$resource["name"]?>" data-href="<?=BigTreeCMS::replaceRelativeRoots($resource["file"])?>">
	<span class="icon_small icon_small_file_default icon_small_file_<?= $resource["type"] ?>"></span> <?=$resource["name"]?>
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
<script>
	<?php if ($_POST["query"]) { ?>
	BigTreeFileManager.setTitleSuffix(": Search Results");
	<?php } else { ?>
	BigTreeFileManager.setTitleSuffix("");
	<?php } ?>
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"','\"',$crumb_contents)?>");
</script>