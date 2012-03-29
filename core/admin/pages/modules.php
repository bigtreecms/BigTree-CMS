<?
	$breadcrumb = array(array("link" => "modules/","title" => "Modules"));
	$module_title = "Modules";
	
	if ($path[2]) {
		$group = $admin->getModuleGroupByRoute($path[2]);
		$modules = $admin->getModulesByGroup($group["id"]);
		
		$module_title = "Modules: " . $group["name"];
		$breadcrumb[] = array("link" => "modules/".$group["route"]."/","title" => $group["name"]);
?>
<h1><span class="modules"></span>Modules: <?=$group["name"]?></h1>
<?
	if (count($modules)) {
?>
<div class="table">
	<summary><h2><?=$group["name"]?></h2></summary>
	<section class="modules">
		<? foreach ($modules as $module) { ?>
		<p class="module">
			<? if ($admin->moduleActionExists($module["id"],"add")) { ?>
			<a href="<?=$admin_root?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<? } ?>
			<a class="module_name" href="<?=$admin_root?><?=$module["route"]?>/"><?=$module["name"]?></a>
		</p>
		<? } ?>
	</section>
</div>
<?
		}
	} else {
?>
<h1><span class="modules"></span>Modules</h1>
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
			<? if ($admin->moduleActionExists($module["id"],"add")) { ?>
			<a href="<?=$admin_root?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<? } ?>
			<a class="module_name" href="<?=$admin_root?><?=$module["route"]?>/"><?=$module["name"]?></a>
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
			<? if ($admin->moduleActionExists($module["id"],"add")) { ?>
			<a href="<?=$admin_root?><?=$module["route"]?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<? } ?>
			<a class="module_name" href="<?=$admin_root?><?=$module["route"]?>/"><?=$module["name"]?></a>
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
	}
?>