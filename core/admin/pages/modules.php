<?php
	namespace BigTree;

	$module_count = 0;
	$groups = ModuleGroup::all("position DESC, id ASC");
	
	$draw_module_list = function($group_name, $modules) {
		global $module_count;
		
		$module_count += count($modules);
?>
<div class="container">
	<div class="container_summary"><h2><?=$group_name?></h2></div>
	<section class="modules">
		<?php foreach ($modules as $module) { ?>
		<p class="module">
			<?php if (ModuleAction::existsForRoute($module->ID, "add")) { ?>
				<a href="<?=ADMIN_ROOT?><?=$module->Route?>/add/" class="add"><span class="icon_small icon_small_add"></span></a>
			<?php } ?>
			<a class="module_name" href="<?=ADMIN_ROOT?><?=$module->Route?>/">
				<?php if ($module->Icon) { ?>
					<span class="icon_small icon_small_<?=$module->Icon?>"></span>
				<?php } ?>
				<?=$module->Name?>
			</a>
		</p>
		<?php } ?>
	</section>
</div>
<?php
	};
	
	foreach ($groups as $group) {
		$modules = Module::allByGroup($group->ID, "position DESC, id ASC");
		
		if (count($modules)) {
			$draw_module_list($group->Name, $modules);
		}
	}
	
	$misc = Module::allByGroup(0, "position DESC, id ASC");

	if (count($misc)) {
		$draw_module_list(Text::translate("Ungrouped"), $misc);
	}
		
	if ($module_count < 1) {
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("No Modules")?></h2></div>
	<section>
		<p><?=Text::translate("You do not have access to any modules (or none exist).")?></p>
	</section>
</div>
<?php
	}
