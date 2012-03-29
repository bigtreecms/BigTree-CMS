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
<a href="#<?=$folder["parent"]?>" class="file folder"><span class="file_type file_type_folder file_type_folder_back"></span> Back</a>
<?	
	}
	
	if ($perm != "n") {
	
		foreach ($items["folders"] as $folder) {
?>
<a href="#<?=$folder["id"]?>" class="file folder<? if ($folder["permission"] == "n") { ?> disabled<? } ?>"><span class="file_type file_type_folder"></span> <?=$folder["name"]?></a>
<?
		}
	
		foreach ($items["resources"] as $resource) {
			$file = str_replace("{wwwroot}",$site_root,$resource["file"]);
			if ($resource["is_image"]) {
				$resource["type"] = "image";
			}
?>
<a href="<?=$resource["file"]?>" class="file"><span class="file_type file_type_<?=$resource["type"]?>"></span> <?=$resource["name"]?></a>
<?
		}
	}
	
	$crumb_contents = "";
	foreach ($bc as $crumb) {
		$crumb_contents .= '<li><a href="#'.$crumb["id"].'">'.$crumb["name"].'</a></li>';
	}
?>
<script type="text/javascript">
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