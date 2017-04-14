<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$page = new Page($_GET["id"]);
	
	if ($page->UserAccessLevel == "p") {
		$page->deleteDraft();
	}
