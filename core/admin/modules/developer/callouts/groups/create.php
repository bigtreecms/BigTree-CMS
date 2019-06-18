<?php
	namespace BigTree;
	
	CSRF::verify();
	
	CalloutGroup::create($_POST["name"], $_POST["callouts"]);
	
	Admin::growl("Developer", "Created Callout Group");
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	