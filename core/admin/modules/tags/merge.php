<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	if (!Tag::exists(Router::$Commands[0])) {
		Admin::catch404();
	}
	
	$tag = new Tag(Router::$Commands[0]);
	$other_tags = SQL::fetchAll("SELECT `id` AS `value`, `tag` AS `title` FROM bigtree_tags ORDER BY `tag`");
?>
<tags-form action="merge" id="<?=$tag->ID?>" tag="<?=$tag->Name?>"
		   :other_tags="<?=htmlspecialchars(json_encode($other_tags))?>"></tags-form>
