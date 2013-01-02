<?
	// Stop notices
	$data["fields"] = isset($data["fields"]) ? $data["fields"] : array(array("key" => "","title" => "","type" => "text"));

	$types = array(
		"text" => "Text",
		"textarea" => "Text Area",
		"html" => "HTML",
		"checkbox" => "Checkbox",
		"date" => "Date Picker",
		"time" => "Time Picker",
	);
?>
<div id="aoi_fields"></div>

<script>	
	new BigTreeListMaker("#aoi_fields","fields","Fields",["Array Key","Title","Type"],[{ key: "key", type: "text" },{ key: "title", type: "text" },{ key: "type", type: "select", list: <?=json_encode($types)?> }],<?=json_encode($data["fields"])?>);
</script>