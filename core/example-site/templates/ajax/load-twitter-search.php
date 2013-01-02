<?
	$twitterAPI = new BTXTwitterAPI;
	$twitterTimeline = $twitterAPI->search($_GET["search"]);
	if ($_GET["sidebar"]) {
		$twitterTimeline["results"] = array_slice($twitterTimeline["results"],0,4);
	}
	
	foreach ($twitterTimeline["results"] as $tweet) {
?>
<article class="tweet">
	<p><?=$tweet["text"]?></p>
	<strong><a href="http://www.twitter.com/<?=$tweet["user"]?>/">@<?=$tweet["user"]?></a> <?=$tweet["created"]?></strong>
</article>
<? 
	}
?>