<?php
	namespace BigTree;

	$admin->requireLevel(1);

	$settings = Setting::all("name ASC",true);
	foreach ($settings as &$item) {
		if ($item["encrypted"]) {
			$item["value"] = "&mdash; Encrypted Value &mdash;";
		} elseif (is_array($item["value"]) || ($item["value"] && !strlen(trim(strip_tags($item["value"]))))) {
			$item["value"] = "&mdash; Edit To View &mdash;";
		} else {
			$item["value"] = Text::trimLength(strip_tags($item["value"]),100);
		}
	}
?>
<div id="settings_table"></div>
<script>
	BigTreeTable({
		container: "#settings_table",
		columns: {
			name: { title: "Name", sort: "asc", size: 0.3 },
			value: { title: "Value" }
		},
		actions: {
			edit: "<?=ADMIN_ROOT?>settings/edit/{id}/"
		},
		data: <?=JSON::encodeColumns($settings,array("id","name","value"))?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>