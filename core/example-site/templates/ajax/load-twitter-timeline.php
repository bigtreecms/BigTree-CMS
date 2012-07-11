<?
	if (class_exists("BTXTwitterAPI")) {
		$twitterAPI = new BTXTwitterAPI;
		$twitterTimeline = $twitterAPI->search($_GET["search"]);
		$twitterCount = floor(count($twitterTimeline["results"]) / 3) * 3;
		$twitterTimeline = array_slice($twitterTimeline["results"], 0, $twitterCount);
	} else {
		$twitterTimeline = false;
	}
	
	foreach ($twitterTimeline as $tweet) {
?>
<article class="tweet">
	<p><?=$tweet["text"]?></p>
	<strong><a href="http://www.twitter.com/<?=$tweet["user"]?>/">@<?=$tweet["user"]?></a> <?=$tweet["created"]?></strong>
</article>
<? 
	}
?>