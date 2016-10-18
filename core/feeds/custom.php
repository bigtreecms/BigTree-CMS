<feed>
	<?
		$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id desc";
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
	?>
	<item>
		<?
			foreach ($feed["fields"] as $key => $options) {
				$value = $item[$key];
				if ($options["parser"]) {
					$value = BigTree::runParser($item,$value,$options["parser"]);
				}
		?>
		<<?=$key?>><![CDATA[<?=$value?>]]></<?=$key?>>
		<?
			}
		?>
	</item>
	<?
		}
	?>
</feed>