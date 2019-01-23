<rss version="2.0">
	<channel>
		<title><?php if ($feed["settings"]["feed_title"]) { echo $feed["settings"]["feed_title"]; } else { echo $feed["name"]; } ?></title>
		<link><?php if ($feed["settings"]["feed_link"]) { echo $feed["settings"]["feed_link"]; } else { ?><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<?php } ?></link>
		<description><?=$feed["description"]?></description>
		<language>en-us</language>
		<generator>BigTree CMS (https://www.bigtreecms.org)</generator>
		<?php
			$sort = $feed["settings"]["sort"] ?: "id DESC";
			$limit = $feed["settings"]["limit"] ?: "15";
			$items = array();
			
			if ($feed["settings"]["parser"]) {
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

			if ($feed["settings"]["parser"]) {
				$items = call_user_func_array($feed["settings"]["parser"], array($items));
				$items = array_slice($items, 0, $limit);
			}

			foreach ($items as $item) {
				if ($feed["settings"]["link_gen"]) {
					$link = $feed["settings"]["link_gen"];
					foreach ($item as $key => $val) {
						$link = str_replace("{".$key."}",$val,$link);
					}
				} else {
					$link = $item[$feed["settings"]["link"]];
				}
				
				$content = $item[$feed["settings"]["description"]];
				$limit = $feed["settings"]["content_limit"] ? $feed["settings"]["content_limit"] : 500;
				$blurb = BigTree::trimLength($content,$limit);
		?>
		<item>
			<guid><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<?=$item["id"]?>/</guid>
			<title><![CDATA[<?=strip_tags($item[$feed["settings"]["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><?php if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><?php } ?>]]></description>
			<link><?=$link?></link>
			<?php
				if ($feed["settings"]["creator"]) {
			?>
			<author><?=$item[$feed["settings"]["creator"]]?></author>
			<?php
				}
				
				if ($feed["settings"]["date"]) {
			?>
			<pubDate><?=date("D, d M Y H:i:s T",strtotime($item[$feed["settings"]["date"]]))?></pubDate>
			<?php
				}
			?>
		</item>
		<?php
			}
		?>
	</channel>
</rss>