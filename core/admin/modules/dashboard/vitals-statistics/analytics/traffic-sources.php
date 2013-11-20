<div class="table">
	<summary>
		<p>Your visitor's traffic sources over the past month.</p>
	</summary>
	<header>
		<span class="analytics_metric_name">Referrer</span>
		<span class="analytics_visit_count">Visit Count</span>
		<span class="analytics_view_count">View Count</span>
	</header>
	<ul id="traffic_sources">
		<?
			if (is_array($cache["referrers"])) {
				foreach ($cache["referrers"] as $source) {
		?>
		<li>
			<section class="analytics_metric_name"><?=ucwords($source["name"])?></section>
			<section class="analytics_visit_count"><?=$source["visits"]?></section>
			<section class="analytics_view_count"><?=$source["views"]?></section>
		</li>
		<?
				}
			} else {
		?>
		<li class="no_content">We have no data yet.</li>
		<?		
			}
		?>
	</ul>
</div>