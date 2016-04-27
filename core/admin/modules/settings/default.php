<?php
	namespace BigTree;

	$admin->requireLevel(1);
	$settings = Setting::all("name ASC", true);

	foreach ($settings as $key => $item) {
		if ($item["system"] || $item["locked"] && $admin->Level < 2) {
			unset($settings[$key]);
		} else {
			if ($item["encrypted"]) {
				$settings[$key]["value"] = "&mdash; ".Text::translate("Encrypted Value")." &mdash;";
			} else {
				$item["value"] = json_decode($item["value"], true);
				
				if (is_array($item["value"]) || ($item["value"] && !strlen(trim(strip_tags($item["value"]))))) {
					$settings[$key]["value"] = "&mdash; ".Text::translate("Edit To View")." &mdash;";
				} else {
					$settings[$key]["value"] = Text::trimLength(strip_tags($item["value"]),100);
				}
			}
		}
	}
?>
<div id="settings_table"></div>
<script>
	BigTreeTable({
		container: "#settings_table",
		columns: {
			name: { title: "<?=Text::translate("Name", true)?>", sort: "asc", size: 0.3 },
			value: { title: "<?=Text::translate("Value", true)?>" }
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