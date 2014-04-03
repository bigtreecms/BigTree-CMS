<feed>
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