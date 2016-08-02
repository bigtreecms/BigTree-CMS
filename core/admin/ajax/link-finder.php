<?php
	namespace BigTree;
	
	$results = Page::search($_POST["query"], array("nav_title"), 10, true);
?>
<span>Drag Items Into Any Content Area</span>
<?php foreach ($results as $page) { ?>
<a href="<?=WWW_ROOT.$page["path"]."/"?>" title="<?=$page["title"]?>"><?=$page["nav_title"]?></a>
<?php } ?>