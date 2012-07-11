<?
	header("Content-type: text/xml");
	$settings = $cms->getSetting("btx-dogwood-settings");
?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<title><?=$settings["title"]?> RSS Feed</title>
		<link><?=$blog_link?></link>
		<description></description>
		<language>en-us</language>
		<?
			$posts = $dogwood->getRecentPosts(15);
			foreach ($posts as $post) {
		?>
		<item>
			<title><?=$post["title"]?></title>
			<description><![CDATA[<?=$post["blurb"]?>]]></description>
			<link><?=$blog_link."post/".$post["route"]?>/</link>
			<dc:creator><?=$post["author"]["name"]?></dc:creator>
			<dc:date><?=date("Y-m-d",strtotime($post["date"]))?>T<?=date("H:i:s",strtotime($post["date"]))?>+05:00</dc:date>
		</item>
		<?
			}
		?>
	</channel>
</rss>
<? die(); ?>