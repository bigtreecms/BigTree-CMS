<?
	include BigTree::path($relative_path."_check.php");
	$breadcrumb[] = array("link" => "dashboard/vitals-statistics/analytics/traffic-sources/", "title" => "Traffic Sources");

	$cache = $cms->getSetting("bigtree-internal-google-analytics-cache");
	
	if (!$cache) {
		header("Location: setup/");
	}
?>
<h1>
	<span class="analytics"></span>Traffic Sources
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<? include BigTree::path($relative_path."_nav.php") ?>
<div class="table">
	<summary>
		<p>This report shows the traffic sources for your visitors in the past month.</p>
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