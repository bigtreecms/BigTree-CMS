<?php
	/**
	 * @global array $cache
	 */
?>
<div class="table">
	<summary>
		<p>Which web browsers your visitors have been using over the past month.</p>
	</summary>
	<header>
		<span class="analytics_metric_name">Browser</span>
		<span class="analytics_visit_count">Visit Count</span>
		<span class="analytics_view_count">View Count</span>
	</header>
	<ul>
		<?php
			if (is_array($cache["browsers"])) {
				foreach ($cache["browsers"] as $browser => $data) {
		?>
		<li>
			<section class="analytics_metric_name"><?=$browser?></section>
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