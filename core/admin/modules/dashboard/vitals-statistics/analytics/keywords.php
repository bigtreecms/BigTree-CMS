<?
	include BigTree::path($relative_path."_check.php");
	$breadcrumb[] = array("link" => "dashboard/analytics/vitals-statistics/keywords/", "title" => "Keywords");

	$cache = $cms->getSetting("bigtree-internal-google-analytics-cache"); 
	
	if (!$cache) {
		header("Location: setup/");
	}
?>
<h1>
	<span class="analytics"></span>Keywords
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<? include BigTree::path($relative_path."_nav.php") ?>
<div class="table">
	<summary>
		<p>This report shows the search keywords for your visitors in the past 30 days.</p>
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