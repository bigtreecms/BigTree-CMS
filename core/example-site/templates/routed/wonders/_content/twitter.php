<?
	if (class_exists("BTXTwitterAPI")) {
		$twitterAPI = new BTXTwitterAPI;
		$twitterTimeline = $twitterAPI->search($currentWonder["title"]);
		$twitterCount = floor(count($twitterTimeline["results"]) / 3) * 3;
		$twitterTimeline = array_slice($twitterTimeline["results"], 0, $twitterCount);
	} else {
		$twitterTimeline = false;
	}
?>
<div class="grid_3 info">
	<h4>The Sounds</h4>
	<hr />
	<a href="http://www.twitter.com/" class="more" target="_blank">Overheard on Twitter</a>
</div>
<div class="timeline">
	<div class="inner">
		<?
			foreach ($twitterTimeline as $tweet) {
		?>
		<article class="grid_3 tweet">
			<p>
				<?=$tweet["text"]?>
			</p>
			<strong><a href="http://www.twitter.com/<?=$tweet["user"]?>/">@<?=$tweet["user"]?></a> <?=$tweet["created"]?></strong>
		</article>
		<? 
			}
		?>
	</div>
</div>