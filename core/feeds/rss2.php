<rss version="2.0">
	<channel>
		<title><?php if ($feed["options"]["feed_title"]) { echo $feed["options"]["feed_title"]; } else { echo $feed["name"]; } ?></title>
		<link><?php if ($feed["options"]["feed_link"]) { echo $feed["options"]["feed_link"]; } else { ?><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<?php } ?></link>
		<description><?=$feed["description"]?></description>
		<language>en-us</language>
		<generator>BigTree CMS (http://www.bigtreecms.org)</generator>
		<?php
			$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id DESC";
			$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";
			$items = array();
			
			if ($feed["options"]["parser"]) {
				$q = sqlquery("SELECT * FROM ".$feed["table"]." ORDER BY $sort");
			} else {
				$q = sqlquery("SELECT * FROM ".$feed["table"]." ORDER BY $sort LIMIT $limit");
			}

			while ($item = sqlfetch($q)) {
				foreach ($item as $key => $val) {
					if (is_array(json_decode($val,true))) {
						$item[$key] = BigTree::untranslateArray(json_decode($val,true));
					} else {
						$item[$key] = $cms->replaceInternalPageLinks($val);
					}
				}

				$items[] = $item;
			}

			if ($feed["options"]["parser"]) {
				$items = call_user_func_array($feed["options"]["parser"], array($items));
				$items = array_slice($items, 0, $limit);
			}

			foreach ($items as $item) {
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
			<guid><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<?=$item["id"]?>/</guid>
			<title><![CDATA[<?=strip_tags($item[$feed["options"]["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><?php if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><?php } ?>]]></description>
			<link><?=$link?></link>
			<?php
				if ($feed["options"]["creator"]) {
			?>
			<author><?=$item[$feed["options"]["creator"]]?></author>
			<?php
				}
				
				if ($feed["options"]["date"]) {
			?>
			<pubDate><?=date("D, d M Y H:i:s T",strtotime($item[$feed["options"]["date"]]))?></pubDate>
			<?php
				}
			?>
		</item>
		<?php
			}
		?>
	</channel>
</rss>