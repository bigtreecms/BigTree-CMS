<?php
	$extension_settings = $cms->getSetting("bigtree-internal-extension-settings");
	$settings = $extension_settings["dashboard"];

	$panes = array();
	$positions = array();

	// We're going to get the position setups and the multi-sort the list to get it in order
	foreach (BigTreeAdmin::$DashboardPlugins["core"] as $id => $name) {
		$panes[] = array(
			"id" => $id,
			"name" => $name,
			"approved" => empty($settings[$id]["disabled"]) ? "on" : ""
		);
		$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
	}
	foreach (BigTreeAdmin::$DashboardPlugins["extension"] as $extension => $set) {
		foreach ($set as $id => $name) {
			$id = $extension."*".$id;
			$panes[] = array(
				"id" => $id,
				"name" => $name,
				"approved" => empty($settings[$id]["disabled"]) ? "on" : ""
			);
			$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
		}
	}
	array_multisort($positions,SORT_DESC,$panes);
?>
<div id="dashboard_pane_table"></div>
<script>
	BigTreeTable({
		container: "#dashboard_pane_table",
		title: "Dashboard Panes",
		columns: { name: { title: "Name", largeText: true } },
		actions: {
			approve: function(id,state) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/toggle-extension-plugin/", { type: "POST", data: { type: "dashboard", id: id, state: state } });
			}
		},
		searchable: true,
		draggable: function(positions) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/order-extension-plugins/", { type: "POST", data: { type: "dashboard", positions: positions } });
		},
		data: <?=json_encode($panes)?>
	});
</script>