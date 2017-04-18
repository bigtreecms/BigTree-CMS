<?php
	namespace BigTree;
	
	CSRF::verify();
	
	foreach ($_POST as $key => $val) {
		$_SESSION["bigtree_admin"]["developer"]["package"][$key] = $val;
	}

	if (!ctype_alnum(str_replace(array("-","_","."),"",$_POST["id"]))) {
		Utils::growl("Developer","Package ID contains invalid characters.");
		Router::redirect(DEVELOPER_ROOT."packages/build/details/?invalid=true");	
	} else {
		Router::redirect(DEVELOPER_ROOT."packages/build/components/");
	}
	