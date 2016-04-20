<?php
	namespace BigTree;

	// Get publishable changes.
	$changes = $admin->getPublishableChanges($admin->ID);
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
				$change_modules[0] = array("title" => Text::translate("Pages"), "count" => 1);
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
				$my_change_modules[0] = array("title" => Text::translate("Pages"), "count" => 1);
			} else {
				$module = $admin->getModule($c["module"]);
				$my_change_modules[$c["module"]] = array("title" => $module["name"], "icon" => $module["icon"], "count" => 1);
			}
		} else {
			$my_change_modules[$c["module"]]["count"]++;
		}
	}
?>
<div class="table pending_changes_table">
	<summary>
		<h2 class="full">
			<span class="pending"></span>
			<?=Text::translate("Pending Changes")?>
			<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/" class="button"><?=Text::translate("View All Pending Changes")?></a>
		</h2>
	</summary>
	
	<div class="split left">
		<?php
			if (!count($changes)) {
		?>
		<section class="no_content">
			<p><?=Text::translate("There are no changes awaiting your approval.")?></p>
		</section>
		<?php
			} else {
		?>
		<h3><?=Text::translate("Changes Pending Your Approval")?></h3>
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
				<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/#<?=$m?>"><span class="icon_small icon_small_<?=$icon?>"></span> <?=$cm["count"]?> <?=Text::translate($cm["count"] == 1 ? "change" : "changes")?> <?=Text::translate("for")?> <?=$cm["title"]?></a>
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
			<p><?=Text::translate("You have no changes awaiting a publisher's approval.")?></p>
		</section>
		<?php
			} else {
		?>
		<h3><?=Text::translate("Your Changes Pending Approval")?></h3>
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
				<span class="icon_small icon_small_<?=$icon?>"></span> <?=$cm["count"]?> <?=Text::translate($cm["count"] == 1 ? "change" : "changes")?> <?=Text::translate("for")?> <?=$cm["title"]?>
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