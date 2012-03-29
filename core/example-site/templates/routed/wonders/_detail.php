<?
	$introImage = BigTree::prefixFile($currentWonder["image"], "lrg_");
?>
<header class="intro">
	<div class="container_12">
		<h1><a href="<?=$wonderLink?><?=$currentWonder["route"]?>/"><?=$currentWonder["title"]?></a></h1>
		<hr />
		<p>
			<?=$currentWonder["blurb"]?>
		</p>
		<img src="<?=$introImage?>" alt="<?=$currentWonder["title"]?>" />
	</div>
</header>
<? 
	if (class_exists("BTXWikipediaAPI")) { 
?>
<section class="wiki_history loading">
	<div class="container_12">
		Loading History
	</div>
</section>
<?
	}
	if (class_exists("BTXInstagramAPI")) {
		$btxInstagramAPI = new BTXInstagramAPI;
		if ($btxInstagramAPI->client_id) {
?>
<section class="instagram_viewer loading">
	<div class="container_12 contain">
		Loading Sights
	</div>
</section>
<?
		}
	}
	if (class_exists("BTXTwitterAPI")) {
?>
<section class="twitter_timeline loading">
	<div class="container_12">
		Loading Sounds
	</div>
	<div class="triggers">
		<span class="trigger next">next</span>
		<span class="trigger previous disabled">Previous</span>
	</div>
</section>
<?
	}
	if (class_exists("BTXYouTubeAPI")) {
?>
<section class="youtube_videos loading">
	<div class="container_12 contain">
		Loading Visions
	</div>
</section>
<?
	}
?>
<script>
	var wonderId = <?=$currentWonder["id"]?>;
</script>