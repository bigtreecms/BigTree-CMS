<?
	if ($path[3] == "groups") {
		$current = $path[3]."/".$path[4];
	} else {
		$current = $path[3];
	}
	$subnav = array(
		array("route" => "view", "class" => "list", "title" => "View Modules"),
		array("route" => "add", "class" => "add", "title" => "Add Module"),
		array("route" => "designer", "class" => "edit", "title" => "Module Designer"),
		array("route" => "groups/view", "class" => "list", "title" => "View Groups"),
		array("route" => "groups/add", "class" => "add", "title" => "Add Group")
	);
?>
<nav class="sub">
	<ul>
		<? foreach ($subnav as $nav_item) { ?>
		<li><a href="<?=$developer_root?>modules/<?=$nav_item["route"]?>/"<? if ($nav_item["route"] == $current) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$nav_item["class"]?>"></span><?=$nav_item["title"]?></a></li>
		<? } ?>
	</ul>
</nav>