<?php
	namespace BigTree;

	/**
	 * @global string $module_permission (set in ajax file)
	 * @global ModuleView $view
	 */
?>
<auto-modules-view-searchable id="<?=$view->ID?>"
							  module="<?=$view->Module->ID?>"
							  title="<?=$view->Title?>"
							  :fields="<?=htmlspecialchars(json_encode($view->Fields))?>"
							  :actions="<?=htmlspecialchars(json_encode($view->Actions))?>"
							  actions_base_path="<?=$view->Module->Route?>"
							  help_text="<?=$view->Description?>"
							  sort_column="<?=$view->SortColumn?>"
							  sort_direction="<?=$view->SortDirection?>">
</auto-modules-view-searchable>