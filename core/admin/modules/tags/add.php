<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	$other_tags = SQL::fetchAll("SELECT `id` AS `value`, `tag` AS `title` FROM bigtree_tags ORDER BY `tag`");
?>
<tags-form :other_tags="<?=htmlspecialchars(json_encode($other_tags))?>"></tags-form>
