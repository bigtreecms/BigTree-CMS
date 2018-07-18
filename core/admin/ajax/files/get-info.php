<?php
	if (!empty($_POST["file"])) {
		$file = $admin->getResourceByFile($_POST["file"]);
	} else {
		$file = $admin->getResource($_POST["id"]);
	}
	
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
		if ($file["location"] == "YouTube") {
			$embed = '<iframe src="https://youtube.com/embed/'.$file["video_data"]["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
		} elseif ($file["location"] == "Vimeo") {
			$embed = '<iframe src="https://player.vimeo.com/video/'.$file["video_data"]["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
		}
?>
<div class="file_browser_detail_video">
	<?=$embed?>
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
	<p><span>Uploaded</span><strong><?=str_replace(" @ ", "<br>", $admin->convertTimestampToUser($file["date"], $bigtree["config"]["date_format"]." @ g:i a"))?></strong></p>
	<?php
		if ($file["folder"]) {
	?>
	<p><span>Folder</span><strong class="js-folder" data-folder="<?=$folder["id"]?>"><?=$folder["name"]?></strong></p>
	<?php
		}
	?>
</div>