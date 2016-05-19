<?php
	namespace BigTree;

	// Stop notices
	$data["fields"] = isset($data["fields"]) ? $data["fields"] : array(array("key" => "","title" => "","type" => "text"));

	// Fix out of order numeric keys
	$aoi_fields = array();
	foreach ($data["fields"] as $f) {
		$aoi_fields[] = $f;
	}

	$types = array(
		"text" => Text::translate("Text"),
		"textarea" => Text::Translate("Text Area"),
		"html" => Text::translate("HTML"),
		"checkbox" => Text::translate("Checkbox"),
		"date" => Text::translate("Date Picker"),
		"time" => Text::translate("Time Picker")
	);
?>
<div id="aoi_fields"></div>

<script>	
	BigTreeListMaker(
		"#aoi_fields",
		"fields",
		"<?=Text::translate("Fields", true)?>",
		["<?=Text::translate("Array Key", true)?>","<?=Text::translate("Title", true)?>","<?=Text::translate("Type", true)?>"],
		[
			{ key: "key", type: "text" },
			{ key: "title", type: "text" },
			{ key: "type", type: "select", list: <?=json_encode($types)?> }
		],
		<?=json_encode($aoi_fields)?>
	);
</script>