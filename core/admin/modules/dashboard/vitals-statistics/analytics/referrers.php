<?php
	/**
	 * @global array $cache
	 */
?>
<div class="table">
	<summary>
		<p>The domains that are referring traffic most frequently over the past month.</p>
	</summary>
	<header>
		<span class="analytics_metric_name">Referrer</span>
		<span class="analytics_visit_count">Visit Count</span>
		<span class="analytics_view_count">View Count</span>
	</header>
	<ul id="traffic_sources">
		<?php
			if (is_array($cache["referrers"])) {
				foreach ($cache["referrers"] as $source => $data) {
		?>
		<li>
			<section class="analytics_metric_name"><?=$source?></section>
			<section class="analytics_visit_count"><?=$data["sessions"]?></section>
			<section class="analytics_view_count"><?=$data["screenPageViews"]?></section>
		</li>
		<?php
				}
			} else {
		?>
		<li class="no_content">We have no data yet.</li>
		<?php
			}
		?>
	</ul>
</div>