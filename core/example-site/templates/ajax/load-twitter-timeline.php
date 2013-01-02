<?
	$twitterAPI = new BTXTwitterAPI;
	$twitterTimeline = $twitterAPI->timeline($_GET["timeline"]);
	if ($_GET["sidebar"]) {
		$twitterTimeline = array_slice($twitterTimeline,0,4);
	}
	
	foreach ($twitterTimeline as $tweet) {
?>
<article class="tweet">
	<p><?=$tweet["text"]?></p>
	<strong><a href="http://www.twitter.com/<?=$_GET["timeline"]?>/">@<?=$_GET["timeline"]?></a> <?=$tweet["created"]?></strong>
</article>
<? 
	}
?>