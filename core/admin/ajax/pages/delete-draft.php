<?php
	namespace BigTree;
	
	$page = new Page($_GET["id"]);
	
	if ($page->UserAccessLevel == "p") {
		$page->deleteDraft();
	}
