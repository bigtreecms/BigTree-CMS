<?php
	namespace BigTree;

	// Get changes that the user made and can publish
	$user_id = Auth::user()->ID;
	$changes = PendingChange::allPublishableByUser(new User($user_id));
	$my_changes = PendingChange::allByUser($user_id, "date DESC");
	$modules = DB::getAll("modules");
	
	// Figure out what module each of the changes is for.
	$change_modules = [];
	$my_change_modules = [];
	
	foreach ($changes as $change) {
		// If we didn't get the info for this module already, get it.
		if (!$change->Module) {
			$change->Module = "pages";
		}
		
		if (!array_key_exists($change->Module, $change_modules)) {
			if ($change->Table == "bigtree_pages") {
				$change_modules["pages"] = ["title" => "Pages", "count" => 1];
			} else {
				foreach ($modules as $module) {
					if ($module["id"] == $change->Module) {
						$change_modules[$module["id"]] = [
							"title" => $module["name"],
							"icon" => $module["icon"],
							"count" => 1
						];
					}
				}
			}
		} else {
			$change_modules[$change->Module]["count"]++;
		}
	}
	
	$my_change_modules = [];
	
	foreach ($my_changes as $change) {
		// If we didn't get the info for this module already, get it.
		if (!$change->Module) {
			$change->Module = "pages";
		}
		
		if (!array_key_exists($change->Module, $my_change_modules)) {
			if ($change->Table == "bigtree_pages") {
				$my_change_modules["pages"] = ["title" => "Pages", "count" => 1];
			} else {
				foreach ($modules as $module) {
					if ($module["id"] == $change->Module) {
						$my_change_modules[$module["id"]] = [
							"title" => $module["name"],
							"icon" => $module["icon"],
							"count" => 1
						];
					}
				}
			}
		} else {
			$my_change_modules[$change->Module]["count"]++;
		}
	}
?>
<div class="table pending_changes_table">
	<div class="table_summary">
		<h2 class="full">
			<span class="pending"></span>
			<?=Text::translate("Pending Changes")?>
			<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/" class="button"><?=Text::translate("View All Pending Changes")?></a>
		</h2>
	</div>
	
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
				foreach ($change_modules as $module_id => $module_info) {
					if ($module_id == 0) {
						$icon = "page";
					} elseif ($module_info["icon"]) {
						$icon = $module_info["icon"];
					} else {
						$icon = "gear";
					}
			?>
			<div>
				<a href="<?=ADMIN_ROOT?>dashboard/pending-changes/#<?=$module_id?>"><span class="icon_small icon_small_<?=$icon?>"></span> <?=$module_info["count"]?> <?=Text::translate($module_info["count"] == 1 ? "change" : "changes")?> <?=Text::translate("for")?> <?=$module_info["title"]?></a>
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
				foreach ($my_change_modules as $module_id => $module_info) {
					if ($module_id == 0) {
						$icon = "page";
					} elseif ($module_info["icon"]) {
						$icon = $module_info["icon"];
					} else {
						$icon = "gear";
					}
			?>
			<div>
				<span class="icon_small icon_small_<?=$icon?>"></span> <?=$module_info["count"]?> <?=Text::translate($module_info["count"] == 1 ? "change" : "changes")?> <?=Text::translate("for")?> <?=$module_info["title"]?>
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