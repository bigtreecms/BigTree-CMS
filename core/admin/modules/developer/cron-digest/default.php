<?
	$cron_table_data = array();
	foreach (BigTreeAdmin::$CronPlugins as $extension => $plugins) {
		foreach ($plugins as $id => $details) {
			$cron_table_data[] = array("id" => $extension."*".$id,"name" => $details["name"]);
		}
	}
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
		<div id="cron_tab"></div>
		<script>
			BigTreeTable({
				actions: {
					approve: function(id,state) { console.log(id,state); }
				},
				container: "#cron_tab",
				columns: { name: { title: "Plugin Name" } },
				data: <?=json_encode($cron_table_data)?>,
				draggable: function(positioning) {
					console.log(positioning);
				}
			});
		</script>
	</section>
	<section id="digest_tab" style="display: none;">
		<h3>Daily Digest Plugins</h3>
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
