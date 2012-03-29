<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<title><? if ($feed["options"]["feed_title"]) { echo $feed["options"]["feed_title"]; } else { echo $feed["name"]; } ?></title>
		<link><? if ($feed["options"]["feed_link"]) { echo $feed["options"]["feed_link"]; } else { ?><?=$www_root?>feeds/<?=$feed["route"]?>/<? } ?></link>
		<description><?=$feed["description"]?></description>
		<language>en-us</language>
		<?
			$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id desc";
			$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";

			$q = sqlquery("SELECT * FROM ".$feed["table"]." ORDER BY $sort LIMIT $limit");
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
				$time = strtotime($item[$feed["options"]["date"]]);
		?>
		<item>
			<guid><?=$www_root?>feeds/<?=$feed["route"]?>/<?=$f["id"]?></guid>
			<title><![CDATA[<?=strip_tags($item[$feed["options"]["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><? if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><? } ?>]]></description>
			<link><?=$link?></link>
			<dc:creator><?=$item[$feed["options"]["creator"]]?></dc:creator>
			<dc:date><?=date("Y-m-d",$time)."T".date("H:i:sP",$time)?></dc:date>
		</item>
		<?
			}
		?>
	</channel>
</rss>