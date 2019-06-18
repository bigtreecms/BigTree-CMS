<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$field_type = new FieldType($_POST["id"]);
	$field_type->update($_POST["name"], $_POST["use_cases"], $_POST["self_draw"] ? true : false);
	
	Admin::growl("Developer", "Updated Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/");
	