<?php
	namespace BigTree;
	
	$admin->updateFieldType($_POST["id"],$_POST["name"],$_POST["use_cases"],$_POST["self_draw"]);
	
	Utils::growl("Developer","Updated Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/");
	