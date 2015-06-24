<?php
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");
	$panes = array();
	$positions = array();

	// We're going to get the position setups and the multi-sort the whole shebang
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
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/toggle-pane/", { type: "POST", data: { id: id, state: state } });
			}
		},
		searchable: true,
		draggable: function(positions) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/order-panes/", { type: "POST", data: { positions: positions } });
		},
		data: <?=json_encode($panes)?>
	});
</script>