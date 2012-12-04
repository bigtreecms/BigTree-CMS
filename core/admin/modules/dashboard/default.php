<?
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."dashboard/update/");
	}
	
	// Get all the messages we've received.
	$messages = $admin->getMessages();
	$unread = $messages["unread"];
	$read = $messages["read"];
	$sent = $messages["sent"];
	
	// Get pending changes.
	$changes = $admin->getPendingChanges();
	// Figure out what module each of the changes is for.
	$change_modules = array();
	foreach ($changes as $c) {
		// If we didn't get the info for this module already, get it.
		if (!$c["module"]) {
			$c["module"] = 0;
		}
		if (!array_key_exists($c["module"],$change_modules)) {
			// Pages
			if ($c["module"] == 0) {
				$change_modules[0] = array("title" => "Pages", "count" => 1);
			} else {
				$module = $admin->getModule($c["module"]);
				$change_modules[$c["module"]] = array("title" => $module["name"], "icon" => $module["icon"], "count" => 1);
			}
		} else {
			$change_modules[$c["module"]]["count"]++;
		}
	}
	
	// Get Google Analytics Traffic
	if (file_exists(SERVER_ROOT."cache/analytics.cache")) {
		$ga_cache = json_decode(file_get_contents(SERVER_ROOT."cache/analytics.cache"),true);
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
		<?
			if ($visits) {
		?>
		<div class="graph">
			<?
				$x = 0;
				foreach ($visits as $date => $count) {
					$height = round($bar_height * ($count - $min) / $max) + 12;
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
			   	foreach ($visits as $date => $count) {
			   		$x++;
			?>
			<section class="date<? if ($x == 14) { ?> last<? } elseif ($x == 1) { ?> first<? } ?>"><?=date("n/j/y",strtotime($date))?></section>
			<?
				}
			?>
		</div>
		<?
			} else {
		?>
		<p>No recent traffic</p>
		<?
			}
		?>
	</section>
</div>
<?
	}
?>

<div class="table pending_changes_table">
	<summary>
		<h2 class="full">
			<span class="pending"></span>
			Pending Changes <small>Recent Changes Awaiting Approval</small>
			<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/" class="more">View All Pending Changes</a>
		</h2>
	</summary>
	<ul>
		<?
			if (count($changes) == 0) {
		?>
		<li><section class="no_content"><p>No changes awaiting approval</p></section></li>
		<?	
			} else {
		?>
		<li>
			<section class="changes_awaiting">
				<p>You have the following changes pending your approval:</p>
				<?
					foreach ($change_modules as $m => $cm) {
						if ($m == 0) {
							$icon = "page";
						} elseif ($cm["icon"]) {
							$icon = $cm["icon"];
						} else {
							$icon = "gear";
						}
				?>
				<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/#<?=$m?>"><span class="icon_small icon_small_<?=$icon?>"></span> <?=$cm["count"]?> change<? if ($cm["count"] != 1) { ?>s<? } ?> for <?=$cm["title"]?></a>
				<?
					}
				?>
			</section>
		</li>
		<?
			}
		?>
	</ul>
</div>

<div class="table">
	<summary>
		<h2 class="full">
			<span class="unread"></span>
			Unread Messages
			<a href="<?=ADMIN_ROOT?>dashboard/messages/" class="more">View All Messages</a>
		</h2>
	</summary>
	<header>
		<span class="messages_from_to">From</span>
		<span class="messages_subject">Subject</span>
		<span class="messages_date_time">Date</span>
		<span class="messages_date_time">Time</span>
		<span class="messages_view">View</span>
	</header>
	<ul>
		<?
			if (count($unread) == 0) {
		?>
		<li><section class="no_content"><p>No unread messages</p></section></li>
		<?
			} else {
				foreach ($unread as $item) {
		?>
		<li>
			<section class="messages_from_to"><span class="gravatar"><img src="<?=BigTree::gravatar($item["sender_email"], 36)?>" alt="" /></span><?=$item["sender_name"]?></section>
			<section class="messages_subject"><?=$item["subject"]?></section>
			<section class="messages_date_time"><?=date("n/j/y",strtotime($item["date"]))?></section>
			<section class="messages_date_time"><?=date("g:ia",strtotime($item["date"]))?></section>
			<section class="messages_view"><a href="<?=ADMIN_ROOT?>dashboard/messages/view/<?=$item["id"]?>/" class="icon_message"></a></section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>