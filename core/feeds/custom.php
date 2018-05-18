<feed>
	<?php
		$sort = $feed["settings"]["sort"] ? $feed["settings"]["sort"] : "id desc";
		$limit = $feed["settings"]["limit"] ? $feed["settings"]["limit"] : "15";
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
	?>
	<item>
		<?php
			foreach ($feed["fields"] as $key => $field) {
				$value = $item[$key];
				
				if ($field["parser"]) {
					$value = BigTree::runParser($item,$value,$field["parser"]);
				}
		?>
		<<?=$key?>><![CDATA[<?=$value?>]]></<?=$key?>>
		<?php
			}
		?>
	</item>
	<?php
		}
	?>
</feed>