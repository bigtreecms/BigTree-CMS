<?php
	namespace BigTree;
	
	Router::setLayout("new");
	$current_page = isset(Router::$Commands[0]) ? intval(Router::$Commands[0]) : 0;
?>
<pages-list current_page="<?=$current_page?>"></pages-list>