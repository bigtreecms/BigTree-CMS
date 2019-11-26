<?php
	namespace BigTree;
	
	/**
	 * @global ModuleView $view
	 */
	
	$draggable = (!empty($view->Settings["draggable"]) && $view->Module->UserAccessLevel == "p");
	$prefix = !empty($view->Settings["prefix"]) ? $view->Settings["prefix"] : "";
	$sort_column = $sort_direction = null;
	$fields = htmlspecialchars(json_encode(array_merge([[
		"type" => "image",
		"title" => "Image",
		"prefix" => $prefix
	]], $view->Fields)));
	$actions = htmlspecialchars(json_encode($view->Actions));
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
	
	// Split out sort info if not draggable
	if (!$draggable) {
		$sort_direction = $view->Settings["sort"] ?: "DESC";
		$sort_column = "id";
	}
?>
<module-view-grouped id="<?=$view->ID?>" module="<?=$view->Module->ID?>"
					 title="<?=$view->Title?>" help_text="<?=$view->Description?>"
					 draggable="<?=$draggable?>"
					 sort_column="<?=$sort_column?>" sort_direction="<?=$sort_direction?>"
					 :groups="<?=$groups?>"
					 :fields="<?=$fields?>"
					 :actions="<?=$actions?>"
					 actions_base_path="<?=$view->Module->Route?>">
</module-view-grouped>
