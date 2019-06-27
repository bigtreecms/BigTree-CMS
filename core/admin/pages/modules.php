<?php
	namespace BigTree;
	
	Router::setLayout("new");
	Admin::registerRuntimeJavascript("views/structure/toggle-section.js");
	Admin::registerRuntimeJavascript("views/structure/action-menu.js");
	Admin::registerRuntimeJavascript("views/components/table-component.js");
	Admin::setState([
		"page_title" => "Modules"
	]);

	$module_count = 0;
	$groups = ModuleGroup::all("position DESC, id ASC");
	
	$draw_module_list = function($group_name, $modules) {
		global $module_count;
		
		$module_count += count($modules);
?>
<toggle-section title="<?=$group_name?>">
	<table-component :columns="[{ 'title': 'Module Name', 'key': 'name' }]" :actions="[
		{ 'title': 'View Whatever', 'url': '#' },
		{ 'title': 'Edit Stuff', 'url': 'edit' }
	]" :data="[
		{ 'name': 'Test Module' },
		{ 'name': 'Another Module' }
	]"></table-component>
</toggle-section>
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
?>