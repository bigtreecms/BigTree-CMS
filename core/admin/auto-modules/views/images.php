<?php
	namespace BigTree;

	/**
	 * @global ModuleView $view
	 */
	
	Router::setLayout("new");
	
	$draggable = (!empty($view->Settings["draggable"]) && Router::$Module->UserAccessLevel === "p") ? true : false;
	$prefix = !empty($view->Settings["prefix"]) ? $view->Settings["prefix"] : "";
	$fields = htmlspecialchars(json_encode(array_merge([[
		"type" => "image",
		"title" => "Image",
		"prefix" => $prefix
	]], $view->Fields)));
	$actions = htmlspecialchars(json_encode($view->Actions));
	
	if ($draggable) {
?>
<module-view-draggable id="<?=$view->ID?>"
					   module="<?=$view->Module->ID?>"
					   title="<?=$view->Title?>"
					   draggable="true"
					   :fields="<?=$fields?>"
					   :actions="<?=$actions?>"
					   actions_base_path="<?=$view->Module->Route?>"
					   help_text="<?=$view->Description?>">
</module-view-draggable>
<?php
	} else {
?>
<module-view-searchable id="<?=$view->ID?>"
						module="<?=$view->Module->ID?>"
						title="<?=$view->Title?>"
						:fields="<?=$fields?>"
						:actions="<?=$actions?>"
						actions_base_path="<?=$view->Module->Route?>"
						help_text="<?=$view->Description?>"
						sort_column="<?=$view->SortColumn?>"
						sort_direction="<?=$view->SortDirection?>">
</module-view-searchable>
<?php
	}
?>