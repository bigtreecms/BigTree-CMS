<div class="table">
	<summary>
		<p>Your visitor's search keywords over the past month.</p>
	</summary>
	<header>
		<span class="analytics_metric_name">Keyword</span>
		<span class="analytics_visit_count">Visit Count</span>
		<span class="analytics_view_count">View Count</span>
	</header>
	<ul>
		<?
			if (is_array($cache["keywords"])) {
				foreach ($cache["keywords"] as $keyword) {
		?>
		<li>
			<section class="analytics_metric_name"><?=ucwords($keyword["name"])?></section>
			<section class="analytics_visit_count"><?=$keyword["visits"]?></section>
			<section class="analytics_view_count"><?=$keyword["views"]?></section>
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