<?php
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");

	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."developer/upgrade/scripts/");
	}

	$admin->runHooks("markup", "dashboard-top");
	
	// Get all the messages we've received.
	$messages = $admin->getMessages();
	$unread = $messages["unread"];
	$read = $messages["read"];
	$sent = $messages["sent"];
	
	// Get publishable changes.
	$changes = $admin->getPublishableChanges();
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

	// Get the current user's changes.
	$my_changes = $admin->getPendingChanges();
	// Figure out what module each of the changes is for.
	$my_change_modules = array();

	foreach ($my_changes as $c) {
		// If we didn't get the info for this module already, get it.
		if (!$c["module"]) {
			$c["module"] = 0;
		}

		if (!array_key_exists($c["module"],$my_change_modules)) {
			// Pages
			if ($c["module"] == 0) {
				$my_change_modules[0] = array("title" => "Pages", "count" => 1);
			} else {
				$module = $admin->getModule($c["module"]);
				$my_change_modules[$c["module"]] = array("title" => $module["name"], "icon" => $module["icon"], "count" => 1);
			}
		} else {
			$my_change_modules[$c["module"]]["count"]++;
		}
	}

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
			<?php
				if ($admin->Level) {
			?>
			<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/analytics/" class="more">View Analytics</a>
			<?php
				}
			?>
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

<div class="table pending_changes_table">
	<summary>
		<h2 class="full">
			<span class="pending"></span>
			Pending Changes
			<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/" class="more">View All Pending Changes</a>
		</h2>
	</summary>
	
	<div class="split left">
		<?php
			if (!count($changes)) {
		?>
		<section class="no_content">
			<p>There are no changes awaiting your approval.</p>
		</section>
		<?php
			} else {
		?>
		<h3>Changes Pending Your Approval</h3>
		<section class="changes">
			<?php
				foreach ($change_modules as $m => $cm) {
					if ($m == 0) {
						$icon = "page";
					} elseif ($cm["icon"]) {
						$icon = $cm["icon"];
					} else {
						$icon = "gear";
					}
			?>
			<div>
				<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/#<?=$m?>"><span class="icon_small icon_small_<?=$icon?>"></span> <?=$cm["count"]?> change<?php if ($cm["count"] != 1) { ?>s<?php } ?> for <?=$cm["title"]?></a>
			</div>
			<?php
				}
			?>
		</section>
		<?php
			}
		?>
	</div>
	<div class="split right">
		<?php
			if (!count($my_changes)) {
		?>
		<section class="no_content">
			<p>You have no changes awaiting a publisher's approval.</p>
		</section>
		<?php
			} else {
		?>
		<h3>Your Changes Pending Approval</h3>
		<section class="changes">
			<?php
				foreach ($my_change_modules as $m => $cm) {
					if ($m == 0) {
						$icon = "page";
					} elseif ($cm["icon"]) {
						$icon = $cm["icon"];
					} else {
						$icon = "gear";
					}
			?>
			<div>
				<span class="icon_small icon_small_<?=$icon?>"></span> <?=$cm["count"]?> change<?php if ($cm["count"] != 1) { ?>s<?php } ?> for <?=$cm["title"]?>
			</div>
			<?php
				}
			?>
		</section>
		<?php
			}
		?>
	</div>
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
		<?php
			if (count($unread) == 0) {
		?>
		<li><section class="no_content"><p>No unread messages</p></section></li>
		<?php
			} else {
				foreach ($unread as $item) {
		?>
		<li>
			<section class="messages_from_to"><span class="gravatar"><img src="<?=BigTree::gravatar($item["sender_email"], 36)?>" alt="" /></span><?=$item["sender_name"]?></section>
			<section class="messages_subject"><?=$item["subject"]?></section>
			<section class="messages_date_time"><?=$admin->convertTimestampToUser($item["date"], "n/j/y")?></section>
			<section class="messages_date_time"><?=$admin->convertTimestampToUser($item["date"], "g:i a")?></section>
			<section class="messages_view"><a href="<?=ADMIN_ROOT?>dashboard/messages/view/<?=$item["id"]?>/" class="icon_message"></a></section>
		</li>
		<?php
				}
			}
		?>
	</ul>
</div>

<?php
	$admin->runHooks("markup", "dashboard-bottom");
?>