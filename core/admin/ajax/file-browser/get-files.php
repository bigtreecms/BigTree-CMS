<?
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
<a href="#<?=$folder["parent"]?>" class="file folder back"><span class="icon_small icon_small_back"></span>Back</a>
<?	
	}
	
	foreach ($items["folders"] as $folder) {
?>
<a href="#<?=$folder["id"]?>" class="file folder"><span class="icon_small icon_small_folder"></span> <?=$folder["name"]?></a>
<?
	}
	
	foreach ($items["resources"] as $resource) {
?>
<a <? if ($perm == "n") { ?>href="#" class="file disabled"<? } else { ?>href="<?=$resource["file"]?>" class="file"<? } ?>><span class="icon_small icon_small_file_default icon_small_file_<?=$resource["type"]?>"></span> <?=$resource["name"]?></a>
<?
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
	<? if ($perm == "p") { ?>
	BigTreeFileManager.enableCreate();
	<? } else { ?>
	BigTreeFileManager.disableCreate();
	<? } ?>
	<? if ($_POST["query"]) { ?>
	BigTreeFileManager.setTitleSuffix(": Search Results");
	<? } else { ?>
	BigTreeFileManager.setTitleSuffix("");
	<? } ?>
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"','\"',$crumb_contents)?>");
	<? if ($admin->Level && $_POST["folder"]) { ?>
	BigTreeFileManager.showDeleteFolder();
	<? } else { ?>
	BigTreeFileManager.hideDeleteFolder();
	<? } ?>
</script>