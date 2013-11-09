<?
	$two_week_visits = $cache["two_week"];
	$graph_min = min($two_week_visits);
	$graph_max = max($two_week_visits) - $graph_min;
	$graph_bar_height = 70;
	
	// Get the beginning month of the current quarter
	$current_quarter_month = date("m") - date("m") % 3 + 1;
	
	function _local_compareData($current,$past) {
		if ($past["views"]) {
			$view_growth = number_format((($current["views"]-$past["views"]) / $past["views"]) * 100,2)."%";
		} else {
			$view_growth = "N/A";
		}
		
		if ($past["visits"]) {
			$visits_growth = number_format((($current["visits"]-$past["visits"]) / $past["visits"]) * 100,2)."%";
		} else {
			$visits_growth = "N/A";
		}
		
		if ($past["bounce_rate"]) {
			$bounce_growth = number_format($current["bounce_rate"]-$past["bounce_rate"],2)."%";
		} else {
			$bounce_growth = "N/A";
		}
		
		if ($past["average_time_seconds"]) {
			$time_growth = number_format((($current["average_time_seconds"]-$past["average_time_seconds"]) / $past["average_time_seconds"]) * 100,2)."%";
		} else {
			$time_growth = "N/A";
		}
		
		$c_min = "";
		$c_seconds = floor($current["average_time_seconds"])." second(s)";
		$c_time = $current["average_time_seconds"];
		if ($c_time > 60) {
			$c_minutes = floor($c_time / 60);
			$c_seconds = floor($c_time - ($c_minutes * 60))." second(s)";
			$c_min = $c_minutes." minute(s)";
		}
		$c_time = trim($c_min." ".$c_seconds);
		
		$p_ = "";
		$p_seconds = floor($past["average_time_seconds"])." second(s)";
		$p_time = $past["average_time_seconds"];
		if ($p_time > 60) {
			$p_minutes = floor($p_time / 60);
			$p_seconds = floor($p_time - ($p_minutes * 60))." second(s)";
			$p_min = $p_minutes." minute(s)";
		}
		$p_time = trim($p_min." ".$p_seconds);
		
		if ($view_growth > 5) {
			$view_class = 'growth';
		} elseif ($view_growth < -5) {
			$view_class = 'warning';
		}
		
		if ($visits_growth > 5) {
			$visit_class = 'growth';
		} elseif ($visits_growth < -5) {
			$visit_class = 'warning';
		}
		
		if ($time_growth > 5) {
			$time_class = "growth";
		} elseif ($time_growth < -5) {
			$time_class = "warning";
		}
		
		if ($bounce_growth < -2) {
			$bounce_class = 'growth';
		} elseif ($bounce_growth > 2) {
			$bounce_class = 'warning';
		}
		
		if (!$current["views"]) {
			$current["views"] = 0;
		}
		if (!$past["views"]) {
			$past["views"] = 0;
		}
		if (!$current["visits"]) {
			$current["visits"] = 0;
		}
		if (!$past["visits"]) {
			$past["visits"] = 0;
		}
?>
<div class="set">
	<div class="data">
		<header><small>Growth</small>Views</header>
		<p class="percentage <?=$view_class?>"><?=$view_growth?></p>
		<label>Present</label>
		<p class="value"><?=number_format($current["views"])?></p>
		<label>Year-ago</label>
		<p class="value"><?=number_format($past["views"])?></p>
	</div>
</div>
<div class="set">
	<div class="data">
		<header><small>Growth</small>Visits</header>
		<p class="percentage <?=$visit_class?>"><?=$visits_growth?></p>
		<label>Present</label>
		<p class="value"><?=number_format($current["visits"])?></p>
		<label>Year-ago</label>
		<p class="value"><?=number_format($past["visits"])?></p>
	</div>
</div>
<div class="set">
	<div class="data">
		<header><small>Growth</small>Average Time on Site</header>
		<p class="percentage <?=$time_class?>"><?=$time_growth?></p>
		<label>Present</label>
		<p class="value"><?=$c_time?></p>
		<label>Year-ago</label>
		<p class="value"><?=$p_time?></p>
	</div>
</div>
<div class="set">
	<div class="data">
		<header><small>Growth</small>Bounce Rate</header>
		<p class="percentage <?=$bounce_class?>"><?=$bounce_growth?></p>
		<label>Present</label>
		<p class="value"><?=number_format($current["bounce_rate"],2)?>%</p>
		<label>Year-ago</label>
		<p class="value"><?=number_format($past["bounce_rate"],2)?>%</p>
	</div>
</div>
<?
	}
?>
<div class="table">
	<summary>
		<h2>Two Week Heads-Up <small>(visits)</small></h2>
	</summary>
	<section>
		<div class="graph">
			<?
				$x = 0;
				$graph_max = ($graph_max < 1) ? 1 : $graph_max;
				foreach ($two_week_visits as $date => $count) {
					$height = round($graph_bar_height * ($count - $graph_min) / $graph_max) + 12;
					$x++;
					if (!$count) {
						$count = 0;
					}
			?>
			<section class="bar<? if ($x == 14) { ?> last<? } elseif ($x == 1) { ?> first<? } ?>" style="height: <?=$height?>px; margin-top: <?=(82-$height)?>px;">
				<?=$count?>
			</section>
			<?
				}
				
				$x = 0;
				foreach ($two_week_visits as $date => $count) {
					$x++;
			?>
			<section class="date<? if ($x == 14) { ?> last<? } elseif ($x == 1) { ?> first<? } ?>"><?=date("n/j/y",strtotime($date))?></section>
			<?
				}
			?>
		</div>
	</section>
</div>

<section class="analytics_columns">
	<article>
		<summary>Current Month <small>(<?=date("n/1/Y")?> &mdash; <?=date("n/j/Y")?>)</small></summary>
		<? _local_compareData($cache["month"],$cache["year_ago_month"]); ?>
	</article>
	<article>
		<summary>Current Quarter <small>(<?=date("$current_quarter_month/1/Y")?> &mdash; <?=date("n/j/Y")?>)</small></summary>
		<? _local_compareData($cache["quarter"],$cache["year_ago_quarter"]); ?>
	</article>
	<article class="last">
		<summary>Current Year <small>(<?=date("1/1/Y")?> &mdash; <?=date("n/j/Y")?>)</small></summary>
		<? _local_compareData($cache["year"],$cache["year_ago_year"]); ?>
	</article>
</section>