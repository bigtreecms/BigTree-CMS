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
	
	if ($perm != "n") {
	
		foreach ($items["folders"] as $folder) {
?>
<a href="#<?=$folder["id"]?>" class="file folder<? if ($folder["permission"] == "n") { ?> disabled<? } ?>"><span class="icon_small icon_small_folder"></span> <?=$folder["name"]?></a>
<?
		}
	
		foreach ($items["resources"] as $resource) {
?>
<a href="<?=$resource["file"]?>" class="file"><span class="icon_small icon_small_file_default icon_small_file_<?=$resource["type"]?>"></span> <?=$resource["name"]?></a>
<?
		}
	}
	
	$crumb_contents = "";
	foreach ($bc as $crumb) {
		$crumb_contents .= '<li><a href="#'.$crumb["id"].'">'.$crumb["name"].'</a></li>';
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
</script>