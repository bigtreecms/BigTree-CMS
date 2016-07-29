<?php
	namespace BigTree;

	$module_count = 0;
	$groups = $admin->getModuleGroups();
	foreach ($groups as $group) {
		$modules = $admin->getModulesByGroup($group["id"]);
		if (count($modules)) {
?>
<div class="container">
	<summary><h2><?=$group["name"]?></h2></summary>
	<section class="modules">
		<?php
			foreach ($modules as $module) {
		?>
		<p class="module">
			<?php if (ModuleAction::existsForRoute($module["id"], "add")) { ?>
			<a href="<?=ADMIN_ROOT?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<?php } ?>
			<a class="module_name" href="<?=ADMIN_ROOT?><?=$module["route"]?>/"><?php if ($module["icon"]) { ?><span class="icon_small icon_small_<?=$module["icon"]?>"></span><?php } ?><?=$module["name"]?></a>
		</p>
		<?php 
				$module_count++;
			} 
		?>
	</section>
</div>
<?php
		}
	}
	
	$misc = $admin->getModulesByGroup(0);
	if (count($misc)) {
?>
<div class="container">
	<summary><h2><?=Text::translate("Ungrouped")?></h2></summary>
	<section class="modules">
		<?php
			foreach ($misc as $module) {
		?>
		<p class="module">
			<?php if (ModuleAction::existsForRoute($module["id"], "add")) { ?>
			<a href="<?=ADMIN_ROOT?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<?php } ?>
			<a class="module_name" href="<?=ADMIN_ROOT?><?=$module["route"]?>/"><?php if ($module["icon"]) { ?><span class="icon_small icon_small_<?=$module["icon"]?>"></span><?php } ?><?=$module["name"]?></a>
		</p>
		<?php 
				$module_count++;
			} 
		?>
	</section>
</div>
<?php
	}
		
	if ($module_count < 1) {
?>
<div class="container">
	<summary><h2><?=Text::translate("No Modules")?></h2></summary>
	<section>
		<p><?=Text::translate("You do not have access to any modules (or none exist).")?></p>
	</section>
</div>
<?php
	}
?>