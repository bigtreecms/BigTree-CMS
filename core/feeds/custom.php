<feed>
	<?php
		$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id DESC";
		$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";
		$query = SQL::query("SELECT * FROM `".$feed["table"]."` ORDER BY $sort LIMIT $limit");
		
		while ($item = $query->fetch()) {
			foreach ($item as $key => $val) {
				$array_val = @json_decode($val,true);

				if (is_array($array_val)) {
					$item[$key] = BigTree::untranslateArray($array_val);
				} else {
					$item[$key] = $cms->replaceInternalPageLinks($val);
				}
			}
	?>
	<item>
		<?php
			foreach ($feed["fields"] as $key => $options) {
				$value = $item[$key];
				if ($options["parser"]) {
					$value = BigTree::runParser($item,$value,$options["parser"]);
				}

				// If there's a title, use it for a key
				if ($options["title"]) {
					$key = str_replace(" ","",$options["title"]);
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