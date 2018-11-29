<?php
	$admin->runHooks("markup", "modules-top");

	$module_count = 0;
	$groups = $admin->getModuleGroups();
	
	foreach ($groups as $group) {
		$modules = $admin->getModulesByGroup($group["id"]);
		if (count($modules)) {
?>
<div class="table">
	<summary><h2><?=$group["name"]?></h2></summary>
	<section class="modules">
		<?php
			foreach ($modules as $module) {
		?>
		<p class="module">
			<?php if ($admin->doesModuleActionExist($module["id"],"add")) { ?>
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
<div class="table">
	<summary><h2>Ungrouped</h2></summary>
	<section class="modules">
		<?php
			foreach ($misc as $module) {
		?>
		<p class="module">
			<?php if ($admin->doesModuleActionExist($module["id"],"add")) { ?>
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
<div class="table">
	<summary><h2>Ungrouped</h2></summary>
	<ul>
		<li class="no_content">No modules available</li>
	</ul>
</div>
<?php
	}

	$admin->runHooks("markup", "modules-bottom");
?>