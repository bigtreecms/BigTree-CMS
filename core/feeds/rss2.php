<rss version="2.0">
	<channel>
		<title><? if ($feed["options"]["feed_title"]) { echo $feed["options"]["feed_title"]; } else { echo $feed["name"]; } ?></title>
		<link><? if ($feed["options"]["feed_link"]) { echo $feed["options"]["feed_link"]; } else { ?><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<? } ?></link>
		<description><?=$feed["description"]?></description>
		<language>en-us</language>
		<generator>BigTree CMS (http://www.bigtreecms.org)</generator>
		<?
			$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id DESC";
			$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";

			$q = sqlquery("SELECT * FROM `".$feed["table"]."` ORDER BY $sort LIMIT $limit");
			while ($item = sqlfetch($q)) {
				foreach ($item as $key => $val) {
					if (is_array(json_decode($val,true))) {
						$item[$key] = BigTree::untranslateArray(json_decode($val,true));
					} else {
						$item[$key] = $cms->replaceInternalPageLinks($val);
					}
				}
				
				if ($feed["options"]["link_gen"]) {
					$link = $feed["options"]["link_gen"];
					foreach ($item as $key => $val) {
						$link = str_replace("{".$key."}",$val,$link);
					}
				} else {
					$link = $item[$feed["options"]["link"]];
				}
				
				$content = $item[$feed["options"]["description"]];
				$limit = $feed["options"]["content_limit"] ? $feed["options"]["content_limit"] : 500;
				$blurb = BigTree::trimLength($content,$limit);
		?>
		<item>
			<guid><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<?=$f["id"]?></guid>
			<title><![CDATA[<?=strip_tags($item[$feed["options"]["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><? if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><? } ?>]]></description>
			<link><?=$link?></link>
			<?
				if ($feed["options"]["creator"]) {
			?>
			<author><?=$item[$feed["options"]["creator"]]?></author>
			<?
				}
				
				if ($feed["options"]["date"]) {
			?>
			<pubDate><?=date("D, d M Y H:i:s T",strtotime($item[$feed["options"]["date"]]))?></pubDate>
			<?
				}
			?>
		</item>
		<?
			}
		?>
	</channel>
</rss>