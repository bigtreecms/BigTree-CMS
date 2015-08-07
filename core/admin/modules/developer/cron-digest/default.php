<?php
	$extension_settings = $cms->getSetting("bigtree-internal-extension-settings");
	$cron_settings = $extension_settings["cron"];
	$digest_settings = $extension_settings["digest"];

	// Generate data for the cron tab table
	$cron_titles = array();
	$cron_table_data = array();
	foreach (BigTreeAdmin::$CronPlugins as $extension => $plugins) {
		foreach ($plugins as $id => $details) {
			$id = $extension."*".$id;
			$cron_table_data[] = array(
				"id" => $id,
				"name" => $details["name"],
				"approved" => empty($cron_settings[$id]["disabled"]) ? "on" : ""
			);
			$cron_titles[] = $details["name"];
		}
	}
	// Alphabetize the list
	array_multisort($cron_titles,$cron_table_data);

	// Generate daily digest table
	$panes = array();
	$positions = array();

	// We're going to get the position setups and the multi-sort the list to get it in order
	foreach (BigTreeAdmin::$DailyDigestPlugins["core"] as $id => $details) {
		$panes[] = array(
			"id" => $id,
			"name" => $details["name"],
			"approved" => empty($digest_settings[$id]["disabled"]) ? "on" : ""
		);
		$positions[] = isset($digest_settings[$id]["position"]) ? $digest_settings[$id]["position"] : 0;
	}
	foreach (BigTreeAdmin::$DailyDigestPlugins["extension"] as $extension => $set) {
		foreach ($set as $id => $details) {
			$id = $extension."*".$id;
			$panes[] = array(
				"id" => $id,
				"name" => $details["name"],
				"approved" => empty($digest_settings[$id]["disabled"]) ? "on" : ""
			);
			$positions[] = isset($digest_settings[$id]["position"]) ? $digest_settings[$id]["position"] : 0;
		}
	}
	array_multisort($positions,SORT_DESC,$panes);
?>
<div class="container">
	<header>
		<nav class="left">
			<a class="active" href="#cron_tab">Cron</a>
			<a href="#digest_tab">Daily Digest</a>
			<a href="#help_tab">Help</a>
		</nav>
	</header>
	<section id="cron_tab">
		<h3>Cron Plugins</h3>
		<div id="cron_table"></div>
		<script>
			BigTreeTable({
				actions: {
					approve: function(id,state) {
						$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/toggle-extension-plugin/", { type: "POST", data: { type: "cron", id: id, state: state } });
					}
				},
				container: "#cron_table",
				columns: { name: { title: "Plugin Name" } },
				data: <?=json_encode($cron_table_data)?>,
				searchable: true
			});
		</script>
	</section>
	<section id="digest_tab" style="display: none;">
		<h3>Daily Digest Plugins</h3>
		<div id="daily_digest_table"></div>
		<script>
			BigTreeTable({
				container: "#daily_digest_table",
				columns: { name: { title: "Name", largeText: true } },
				actions: {
					approve: function(id,state) {
						$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/toggle-extension-plugin/", { type: "POST", data: { type: "digest", id: id, state: state } });
					}
				},
				searchable: true,
				draggable: function(positions) {
					$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/order-extension-plugins/", { type: "POST", data: { type: "digest", positions: positions } });
				},
				data: <?=json_encode($panes)?>
			});
		</script>
	</section>
	<section id="help_tab" style="display: none;">
		<h3>Cron Setup</h3>
		<p>To setup scheduled sync operations and nightly Daily Digest emails in BigTree, setup a crontab entry with the following command:</p>
		<code>php -f <?=SERVER_ROOT?>core/cron.php > /dev/null</code>

		<br /><br /><br /><br />

		<h3>No Cron?</h3>
		<p>Don't have access to a crontab? BigTree will perform its cron and Daily Digest commands when a user accesses the admin once every 24 hours.</p>
	</section>
</div>
<script>
	BigTreeFormNavBar.init();
</script>
