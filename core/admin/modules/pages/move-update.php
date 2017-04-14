<?php
	namespace BigTree;
	
	CSRF::verify();
	
	if (Auth::user()->Level < 1) {
		$this->stop("You are not allowed to move pages.");
	}
	
	$page = new Page($_POST["page"], false);
	
	// Reset back to not in nav if a non-developer is moving to top level
	if ($this->Level < 2 && $_POST["parent"] == 0) {
		$page->InNav = false;
	}
	
	$page->updateParent($_POST["parent"]);
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	