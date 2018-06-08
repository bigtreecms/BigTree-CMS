<?php
	$file = $admin->getResourceByFile($_POST["file"]);
	$pinfo = BigTree::pathInfo($file["file"]);

	if ($file["folder"]) {
		$folder = $admin->getResourceFolder($file["folder"]);
	}

	// We throw on ?uniqid so that we don't cache the thumbnail in the event that we just replaced it
	if ($file["is_image"]) {
?>
<div class="file_browser_detail_thumb">
	<img src="<?=BigTree::prefixFile($file["file"], "list-preview/")?>" alt="" />
</div>
<?php
	} elseif ($file["is_video"]) {
?>
<div class="file_browser_detail_thumb">
	<img src="<?=BigTree::prefixFile($file["video_data"]["image"], "list-preview/")?>" alt="" />
</div>
<?php
	}
?>
<div class="file_browser_detail_list">
	<p><span>Title</span><strong><?=$file["name"]?></strong></p>
	<?php
		if (!$file["is_image"] && !$file["is_video"]) {
	?>
	<p><span>File Name</span><strong><?=$pinfo["basename"]?></strong></p>
	<?php
		}
	?>
	<p><span>File Type</span><strong><?php if ($file["is_video"]) { echo "Video"; } else { echo $pinfo["extension"]; } ?></strong></p>
	<?php
		if ($file["is_video"]) {
	?>
	<p><span>Service</span><strong><?=$file["location"]?></strong></p>
	<?php
		}

		if ($file["width"]) {
	?>
	<p><span>Width</span><strong><?=$file["width"]?></strong></p>
	<?php
		}

		if ($file["height"]) {
	?>
	<p><span>Height</span><strong><?=$file["height"]?></strong></p>
	<?php
		}
	?>
	<p><span>Uploaded</span><strong><?=date("n/j/y @ g:ia",strtotime($file["date"]))?></strong></p>
	<?php
		if ($file["folder"]) {
	?>
	<p><span>Folder</span><strong class="file_browser_detail_folder_button" data-folder="<?=$folder["id"]?>"><?=$folder["name"]?></strong></p>
	<?php
		}
	?>
</div>