<?php
	// Get Google Analytics Traffic
	if (file_exists(SERVER_ROOT."cache/analytics.json")) {
		$ga_cache = json_decode(file_get_contents(SERVER_ROOT."cache/analytics.json"),true);
	} else {
		$ga_cache = false;
	}
	// Only show this thing if they have Google Analytics setup already
	if ($ga_cache && count($ga_cache["two_week"])) {
		$visits = $ga_cache["two_week"];
		$min = min((is_array($visits)) ? $visits : array($visits));
		$max = max((is_array($visits)) ? $visits : array($visits)) - $min;
		if ($max == 0) {
			$max = 1;
		}
		$bar_height = 70;
?>
<div class="table">
	<summary>
		<h2 class="full">
			<span class="analytics"></span>
			Recent Traffic <small>Visits In The Past Two Weeks</small>
			<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/analytics/" class="more">View Analytics</a>
		</h2>
	</summary>
	<section>
		<?php
			if ($visits) {
		?>
		<div class="graph">
			<?php
				$x = 0;
				foreach ($visits as $date => $count) {
					$height = round($bar_height * ($count - $min) / $max) + 12;
					$x++;
					if (!$count) {
						$count = 0;
					}
			?>
			<section class="bar<?php if ($x == 14) { ?> last<?php } elseif ($x == 1) { ?> first<?php } ?>" style="height: <?=$height?>px; margin-top: <?=(82-$height)?>px;">
				<?=$count?>
			</section>
			<?php
				}
			   	
			   	$x = 0;
			   	foreach ($visits as $date => $count) {
			   		$x++;
			?>
			<section class="date<?php if ($x == 14) { ?> last<?php } elseif ($x == 1) { ?> first<?php } ?>"><?=date("n/j/y",strtotime($date))?></section>
			<?php
				}
			?>
		</div>
		<?php
			} else {
		?>
		<p>No recent traffic</p>
		<?php
			}
		?>
	</section>
</div>
<?php
	}
?>