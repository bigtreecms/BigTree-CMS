<?php
	namespace BigTree;

	CalloutGroup::create($_POST["name"],$_POST["callouts"]);

	$admin->growl("Developer","Created Callout Group");

	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	
	