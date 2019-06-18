<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$callout = new Callout($_POST["id"]);
	$callout->update($_POST["name"], $_POST["description"], $_POST["level"], $_POST["fields"],
					 $_POST["display_field"], $_POST["display_default"]);
	
	Admin::growl("Developer", "Updated Callout");
	Router::redirect(DEVELOPER_ROOT."callouts/");
	