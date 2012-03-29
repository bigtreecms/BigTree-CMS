<?
	if (class_exists("BTXWikipediaAPI")) {
		$wikipediaAPI = new BTXWikipediaAPI;
		$wikiArticle = $wikipediaAPI->article($currentWonder["wiki_url"]);
		$wikiContent = str_ireplace(array("[1]","[2]","[3]","[4]","[5]"), "", getFirstSection($wikiArticle));
	}
?>
<h2>The History</h2>
<?=$wikiContent?>
<a href="<?=$currentWonder["wiki_url"]?>" class="more" target="_blank">Read The Unabridged History On Wikipedia</a>