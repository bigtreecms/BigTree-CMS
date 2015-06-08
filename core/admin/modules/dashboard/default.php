<?php
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."developer/upgrade/database/");
	}

	// Get pane settings
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");

	// Sort the panes
	foreach (BigTreeAdmin::$DashboardPlugins["core"] as $id => $name) {
		$panes[] = array(
			"id" => $id,
			"name" => $name,
			"disabled" => isset($settings[$id]["disabled"]) ? $settings[$id]["disabled"] : ""
		);
		$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
	}
	foreach (BigTreeAdmin::$DashboardPlugins["extension"] as $extension => $set) {
		foreach ($set as $id => $name) {
			$id = $extension."*".$id;
			$panes[] = array(
				"id" => $id,
				"name" => $name,
				"disabled" => isset($settings[$id]["disabled"]) ? $settings[$id]["disabled"] : ""
			);
			$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
		}
	}
	array_multisort($positions,SORT_DESC,$panes);

	// Draw the panes
	foreach ($panes as $pane) {
		if (!$pane["disabled"]) {
			// Core pane
			if (strpos($pane["id"],"*") === false) {
				include BigTree::path("admin/modules/dashboard/panes/".$pane["id"].".php");
			// Extension pane
			} else {
				list($extension,$id) = explode("*",$pane["id"]);
				include SERVER_ROOT."extensions/$extension/plugins/dashboard/$id.php";
			}
		}
	}