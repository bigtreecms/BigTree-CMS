<?php
	namespace BigTree;
	
	Router::setLayout("new");
?>
<pages-list current_page="<?=intval(Router::$Commands[0])?>"></pages-list>