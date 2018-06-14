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
		if ($resource["type"] == "video") {
			$data = json_decode($resource["video_data"], true);
			
			if ($resource["location"] == "YouTube") {
				$embed =  '<iframe src="https://youtube.com/embed/'.$data["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
			} elseif ($resource["location"] == "Vimeo") {
				$embed = '<iframe src="https://player.vimeo.com/video/'.$data["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
			}
?>
<button data-id="<?=$resource["id"]?>" class="file_list_button js-video<?php if ($perm == "n") { ?> disabled<?php } ?>" data-video="<?=$resource["id"]?>" data-name="<?=$resource["name"]?>" data-embed="<?=htmlspecialchars($embed)?>">
	<span class="icon_small icon_small_video"></span> <?=$resource["name"]?>
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