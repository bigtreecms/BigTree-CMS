<?php
	namespace BigTree;

	/**
	 * @global Feed $feed
	 */

	$sort = $feed->Settings["sort"] ? $feed->Settings["sort"] : "id DESC";
	$limit = $feed->Settings["limit"] ? $feed->Settings["limit"] : "15";
	$query = SQL::query("SELECT * FROM `".$feed->Table."` ORDER BY $sort LIMIT $limit");
?><feed>
	<?php
		while ($item = $query->fetch()) {
			foreach ($item as $key => $val) {
				$array_val = @json_decode($val, true);
				$item[$key] = Link::decode(is_array($array_val) ? $array_val : $val);
			}
	?>
	<item>
		<?php
			foreach ($feed->Fields as $key => $options) {
				$value = $item[$key];
				if ($options["parser"]) {
					$value = Module::runParser($item, $value, $options["parser"]);
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