<span>Drag Items Into Any Content Area</span>
<?php
	$results = $admin->searchPages($_POST["query"]);
	foreach ($results as $r) {
?>
<a href="<?=WWW_ROOT.$r["path"]."/"?>" title="<?=$r["title"]?>"><?=$r["nav_title"]?></a>
<?php
	}
?>