<?php
	namespace BigTree;

	/**
	 * @global ModuleView $view
	 */
	
	$draggable = (!empty($view->Settings["draggable"]) && $view->Module->UserAccessLevel == "p");
	$groups = [];
	
	if (!empty($view->Settings["other_table"]) && !empty($view->Settings["title_field"])) {
		$query = "SELECT id, `".str_replace("`", "", $view->Settings["title_field"])."`
				  FROM `".str_replace("`", "", $view->Settings["other_table"])."`";
		
		if (!empty($view->Settings["ot_sort_field"]) && !empty($view->Settings["ot_sort_direction"])) {
			$query .= " ORDER BY `".$view->Settings["ot_sort_field"]."` ".$view->Settings["ot_sort_direction"];
		}
		
		$query = SQL::query($query);
		
		while ($result = $query->fetch()) {
			$groups[$result["id"]] = $result[$view->Settings["title_field"]];
		}
	}
	
	$groups = htmlspecialchars(json_encode($groups));
?>
<module-view-grouped id="<?=$view->ID?>"
					 module="<?=$view->Module->ID?>"
					 title="<?=$view->Title?>"
					 draggable="<?=$draggable?>"
					 :groups="<?=$groups?>"
					 :fields="<?=htmlspecialchars(json_encode($view->Fields))?>"
					 :actions="<?=htmlspecialchars(json_encode($view->Actions))?>"
					 actions_base_path="<?=$view->Module->Route?>"
					 help_text="<?=$view->Description?>">
</module-view-grouped>
