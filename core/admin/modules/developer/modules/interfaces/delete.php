<?php
	$admin->deleteModuleInterface(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Interface");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>