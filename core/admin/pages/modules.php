<?
		$module_count = 0;
		$groups = $admin->getModuleGroups();
		foreach ($groups as $group) {
			$modules = $admin->getModulesByGroup($group["id"]);
			if (count($modules)) {
?>
<div class="table">
	<summary><h2><?=$group["name"]?></h2></summary>
	<section class="modules">
		<?
			foreach ($modules as $module) {
		?>
		<p class="module">
			<? if ($admin->doesModuleActionExist($module["id"],"add")) { ?>
			<a href="<?=ADMIN_ROOT?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<? } ?>
			<a class="module_name" href="<?=ADMIN_ROOT?><?=$module["route"]?>/"><? if ($module["icon"]) { ?><span class="icon_small icon_small_<?=$module["icon"]?>"></span><? } ?><?=$module["name"]?></a>
		</p>
		<? 
				$module_count++;
			} 
		?>
	</section>
</div>
<?
			}
		}
		
		$misc = $admin->getModulesByGroup(0);
		if (count($misc)) {
?>
<div class="table">
	<summary><h2>Ungrouped</h2></summary>
	<section class="modules">
		<?
			foreach ($misc as $module) {
		?>
		<p class="module">
			<? if ($admin->doesModuleActionExist($module["id"],"add")) { ?>
			<a href="<?=ADMIN_ROOT?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<? } ?>
			<a class="module_name" href="<?=ADMIN_ROOT?><?=$module["route"]?>/"><? if ($module["icon"]) { ?><span class="icon_small icon_small_<?=$module["icon"]?>"></span><? } ?><?=$module["name"]?></a>
		</p>
		<? 
				$module_count++;
			} 
		?>
	</section>
</div>
<?
   		}
   		
		if ($module_count < 1) {
?>
<div class="table">
	<summary><h2>Ungrouped</h2></summary>
	<ul>
		<li class="no_content">No modules available</li>
	</ul>
</div>
<?
		}
?>