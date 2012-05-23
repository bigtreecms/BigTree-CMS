<?
	/*
	|Name: Get Navigation Tree|
	|Description: Returns a full navigation tree including hidden and archived pages.  This call is exteremly costly, so it is best to call it only once per session and cache all the data.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: |
	|Returns:
		visible: Visible Pages Array,
		hidden: Hidden Pages Array,
		archived: Archived Pages Array,
		pages: Associtive Array of Pages by Page ID|
	*/
	
	$pages = array();
	
	function recurseBigTreeNav($parent) {
		global $admin,$pages;
		$nav["visible"] = $admin->getNaturalNavigationByParent($parent,1);
		foreach ($nav["visible"] as &$i) {
			$i["access"] = $admin->getPageAccessLevel($i["id"]);
			$pages[$i["id"]] = $i;
			$i["children"] = recurseBigTreeNav($i["id"]);
		}
		$nav["visible"] = array_merge($nav["visible"],$admin->getPendingNavigationByParent($parent));
		$nav["hidden"] = $admin->getHiddenNavigationByParent($parent);
		foreach ($nav["hidden"] as &$i) {
			$i["access"] = $admin->getPageAccessLevel($i["id"]);
			$pages[$i["id"]] = $i;
			$i["children"] = recurseBigTreeNav($i["id"]);
		}		
		$nav["hidden"] = array_merge($nav["hidden"],$admin->getPendingNavigationByParent($parent,""));
		
		$nav["archived"] = $admin->getArchivedNavigationByParent($parent);
		foreach ($nav["archived"] as &$i) {
			$i["access"] = $admin->getPageAccessLevel($i["id"]);
			$pages[$i["id"]] = $i;
		}
		return $nav;
	}

	$nav = recurseBigTreeNav(0);
	
	echo BigTree::apiEncode(array("success" => true,"visible" => $nav["visible"],"hidden" => $nav["hidden"],"archived" => $nav["archived"], "pages" => $pages));
?>