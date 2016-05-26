<?php
	namespace BigTree;
?><rss version="0.91">
	<channel>
		<title><?php if ($feed["options"]["feed_title"]) { echo $feed["options"]["feed_title"]; } else { echo $feed["name"]; } ?></title>
		<link><?php if ($feed["options"]["feed_link"]) { echo $feed["options"]["feed_link"]; } else { ?><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/<?php } ?></link>
		<description><?=$feed["description"]?></description>
		<language>en-us</language>
		<?php
			$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id DESC";
			$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";
			
			$query = SQL::query("SELECT * FROM `".$feed["table"]."` ORDER BY $sort LIMIT $limit");
			while ($item = $query->fetch()) {
				foreach ($item as $key => $val) {
					$array_val = @json_decode($val,true);
	
					if (is_array($array_val)) {
						$item[$key] = Link::decodeArray($array_val);
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
				$blurb = Text::trimLength($content,$limit);
		?>
		<item>
			<title><![CDATA[<?=strip_tags($item[$feed["options"]["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><?php if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><?php } ?>]]></description>
			<link><?=$link?></link>
		</item>
		<?php
			}
		?>
	</channel>
</rss>