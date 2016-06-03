<?php
	namespace BigTree;
	
	if ($_POST["page"] != "0") {
		$admin->updatePageParent($_POST["page"],$_POST["parent"]);
		Utils::growl("Pages","Moved Page");
	}

	Router::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	