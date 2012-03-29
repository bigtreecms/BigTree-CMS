<?
	if (class_exists("BTXYouTubeAPI")) {
		$btxYouTubeAPI = new BTXYouTubeAPI;
		$youTubeVideos = $btxYouTubeAPI->search($currentWonder["title"], 3);
	}
?>
<div class="grid_12 contain center">
	<h3>The Visions</h3>
</div>
<?
	foreach ($youTubeVideos as $item) {
?>
<div class="grid_4 contain">
	<iframe width="300" height="300" src="<?=$item["content"]["src"]?>" frameborder="0" allowfullscreen></iframe>
</div>
<?
	}
?>