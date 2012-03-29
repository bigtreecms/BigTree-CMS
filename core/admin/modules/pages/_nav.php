<?
	$access = $admin->getPageAccessLevel(end($path));
	$available_actions = array(
		array("route" => "view-tree", "name" => "View Subpages", "icon" => "list")
	);
	
	if ($access) {
		$available_actions[] = array("route" => "add", "name" => "Add Subpage", "icon" => "add");
		$available_actions[] = array("route" => "edit", "name" => "Edit Page", "icon" => "edit");
		if (substr($parent,0,1) != "p") {
			$available_actions[] = array("route" => "revisions", "name" => "Revisions", "icon" => "refresh");
		}
	}
	
	if ($admin->Level > 0 && $parent != 0) {
		$available_actions[] = array("route" => "move", "name" => "Move Page", "icon" => "truck");
	}
?>
<nav class="sub">
	<? foreach ($available_actions as $action) { ?>
	<a href="<?=$admin_root?>pages/<?=$action["route"]?>/<?=end($path)?>/"<? if ($path[count($path)-2] == $action["route"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$action["icon"]?>"></span><?=$action["name"]?></a>
	<? } ?>
</nav>