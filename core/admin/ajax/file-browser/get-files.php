<?php
	namespace BigTree;
	
	if ($_POST["query"]) {
		$items = Resource::search($_POST["query"], "date DESC");
		$permission_level = "e";
		$breadcrumb = array(array("name" => Text::translate("Clear Results"), "id" => ""));
	} else {
		$folder = new ResourceFolder($_POST["folder"]);
		$permission_level = $folder->UserAccessLevel;
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
<a href="#<?=$folder["id"]?>" class="file folder"><span class="icon_small icon_small_folder"></span> <?=$folder["name"]?></a>
<?php
	}
	
	foreach ($items["resources"] as $resource) {
?>
<a <?php if ($permission_level == "n") { ?>href="#" class="file disabled" <?php } else { ?>href="<?=$resource["file"]?>" class="file"<?php } ?>><span class="icon_small icon_small_file_default icon_small_file_<?=$resource["type"]?>"></span> <?=$resource["name"]?></a>
<?php
	}
	
	// Make sure the breadcrumb is at most 5 pieces
	$cut_breadcrumb = array_slice($breadcrumb,-5,5);
	
	if (count($cut_breadcrumb) < count($breadcrumb)) {
		$cut_breadcrumb = array_merge(array(array("id" => 0, "name" => "&hellip;")), $cut_breadcrumb);
	}
	
	$crumb_contents = "";
	
	foreach ($cut_breadcrumb as $crumb) {
		$crumb_contents .= '<li><a href="#'.$crumb["id"].'" title="'.$crumb["name"].'">'.$crumb["name"].'</a></li>';
	}
?>
<script>
	<?php if ($permission_level == "p") { ?>
	BigTreeFileManager.enableCreate();
	<?php } else { ?>
	BigTreeFileManager.disableCreate();
	<?php } ?>
	<?php if ($_POST["query"]) { ?>
	BigTreeFileManager.setTitleSuffix(": <?=Text::translate("Search Results")?>");
	<?php } else { ?>
	BigTreeFileManager.setTitleSuffix("");
	<?php } ?>
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"', '\"', $crumb_contents)?>");
	<?php if (Auth::user()->Level && $_POST["folder"]) { ?>
	BigTreeFileManager.showDeleteFolder();
	<?php } else { ?>
	BigTreeFileManager.hideDeleteFolder();
	<?php } ?>
</script>