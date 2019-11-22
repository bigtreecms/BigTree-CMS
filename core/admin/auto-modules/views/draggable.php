<?php
	namespace BigTree;
	
	/**
	 * @global ModuleView $view
	 */
	
	$draggable = ($view->Module->UserAccessLevel == "p");
?>
<module-view-draggable id="<?=$view->ID?>"
					   module="<?=$view->Module->ID?>"
					   title="<?=$view->Title?>"
					   draggable="<?=$draggable?>"
					   :fields="<?=htmlspecialchars(json_encode($view->Fields))?>"
					   :actions="<?=htmlspecialchars(json_encode($view->Actions))?>"
					   actions_base_path="<?=$view->Module->Route?>"
					   help_text="<?=$view->Description?>">
</module-view-draggable>