<?php
	namespace BigTree;
	
	if (!empty($_POST["file"])) {
		$file = Resource::getByFile($_POST["file"]);
	} else {
		$file = new Resource($_POST["id"]);
	}
	
	$pinfo = pathinfo($file["file"]);
	$folder = null;

	if ($file["folder"]) {
		$folder = new ResourceFolder($file["folder"]);
	}

	// We throw on ?uniqid so that we don't cache the thumbnail in the event that we just replaced it
	if ($file->IsImage) {
?>
<div class="file_browser_detail_thumb">
	<img src="<?=FileSystem::getPrefixedFile($file["file"], "list-preview/")?>" alt="" />
</div>
<?php
	} elseif ($file->IsVideo) {
		$embed = "";
		
		if (strtolower($file->Location) == "youtube") {
			$embed = '<iframe src="https://youtube.com/embed/'.$file->VideoData["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
		} elseif (strtolower($file->Location) == "vimeo") {
			$embed = '<iframe src="https://player.vimeo.com/video/'.$file->VideoData["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
		}
?>
<div class="file_browser_detail_video">
	<?=$embed?>
</div>
<?php
	}
?>
<div class="file_browser_detail_list">
	<p><span><?=Text::translate("Title")?></span><strong><?=$file->Name?></strong></p>
	<?php
		if (!$file->IsVideo && !$file->IsImage) {
	?>
	<p><span><?=Text::translate("File Name")?></span><strong><?=$pinfo["basename"]?></strong></p>
	<?php
		}
	?>
	<p><span><?=Text::translate("File Type")?></span><strong><?php if ($file->IsVideo) { echo Text::translate("Video"); } else { echo $pinfo["extension"]; } ?></strong></p>
	<?php
		if ($file->IsVideo) {
	?>
	<p><span><?=Text::translate("Service")?></span><strong><?=$file->Location?></strong></p>
	<?php
		}

		if ($file->Width) {
	?>
	<p><span><?=Text::translate("Width")?></span><strong><?=$file->Width?></strong></p>
	<?php
		}

		if ($file->Height) {
	?>
	<p><span><?=Text::translate("Height")?></span><strong><?=$file->Height?></strong></p>
	<?php
		}
	?>
	<p><span><?=Text::translate("Uploaded")?></span><strong><?=str_replace(" @ ", "<br>", Auth::user()->convertTimestampTo($file["date"], Router::$Config["date_format"]." @ g:i a"))?></strong></p>
	<?php
		if ($folder) {
	?>
	<p><span><?=Text::translate("Folder")?></span><strong class="js-folder" data-folder="<?=$folder->ID?>"><?=$folder->Name?></strong></p>
	<?php
		}
	?>
</div>