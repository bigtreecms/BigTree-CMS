<rss version="0.91">
	<channel>
		<title><? if ($feed["options"]["feed_title"]) { echo $feed["options"]["feed_title"]; } else { echo $feed["name"]; } ?></title>
		<link><? if ($feed["options"]["feed_link"]) { echo $feed["options"]["feed_link"]; } else { ?><?=$www_root?>feeds/<?=$feed["route"]?>/<? } ?></link>
		<description><?=$feed["description"]?></description>
		<language>en-us</language>
		<?
			$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id desc";
			$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";
			
			$am = new BigTreeModule;
			
			$q = sqlquery("SELECT * FROM ".$feed["table"]." ORDER BY $sort LIMIT $limit");
			while ($f = sqlfetch($q)) {
				$f = $am->get($f);
				if ($feed["options"]["link_gen"]) {
					$link = $feed["options"]["link_gen"];
					foreach ($f as $key => $val) {
						$link = str_replace("{".$key."}",$val,$link);
					}
				} else {
					$link = $f[$feed["options"]["link"]];
				}
				
				$content = $f[$feed["options"]["description"]];
				$limit = $feed["options"]["content_limit"] ? $feed["options"]["content_limit"] : 500;
				$blurb = BigTree::trimLength($content,$limit);
		?>
		<item>
			<title><![CDATA[<?=strip_tags($f[$feed["options"]["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><? if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><? } ?>]]></description>
			<link><?=$link?></link>
		</item>
		<?
			}
		?>
	</channel>
</rss>