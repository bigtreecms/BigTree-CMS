<?
	// Send the XML Headers
	header("Content-type: text/xml");
	// Get the Blog's settings so we can draw the title and tagline in the RSS.
	$settings = $cms->getSetting("btx-dogwood-settings");
	// Get the 15 most recent posts.
	$posts = $dogwood->getRecentPosts(15);
?>
<rss version="2.0">
	<channel>
		<title><?=$settings["title"]?></title>
		<link><?=$blog_link?></link>
		<description><?=$settings["tagline"]?></description>
		<language>en-us</language>
		<generator>BigTree CMS (http://www.bigtreecms.org/)</generator>
		<? foreach ($posts as $post) { ?>
		<item>
			<title><?=$post["title"]?></title>
			<description><![CDATA[<?=$post["blurb"]?>]]></description>
			<link><?=$blog_link."post/".$post["route"]?>/</link>
			<author><?=$post["author"]["email"]?></author>
			<pubDate><?=date("D, d M Y H:i:s T",strtotime($post["date"]))?></pubDate>
		</item>
		<? } ?>
	</channel>
</rss>
<? die(); ?>