<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$tag = Tag::create($_POST["tag"]);
	echo $tag->ID;
	