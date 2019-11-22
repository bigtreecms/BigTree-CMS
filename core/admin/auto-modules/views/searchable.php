<?php
	namespace BigTree;

	/**
	 * @global ModuleView $view
	 */
?>
<module-view-searchable id="<?=$view->ID?>"
						module="<?=$view->Module->ID?>"
						title="<?=$view->Title?>"
						:fields="<?=htmlspecialchars(json_encode($view->Fields))?>"
						:actions="<?=htmlspecialchars(json_encode($view->Actions))?>"
						actions_base_path="<?=$view->Module->Route?>"
						help_text="<?=$view->Description?>"
						sort_column="<?=$view->SortColumn?>"
						sort_direction="<?=$view->SortDirection?>">
</module-view-searchable>