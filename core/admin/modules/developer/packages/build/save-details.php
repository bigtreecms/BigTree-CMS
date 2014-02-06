<?
	foreach ($_POST as $key => $val) {
		$_SESSION["bigtree_admin"]["developer"]["package"][$key] = $val;
	}

	if (!ctype_alnum(str_replace(array("-","_","."),"",$_POST["id"]))) {
		$admin->growl("Developer","Package ID contains invalid characters.");
		BigTree::redirect(DEVELOPER_ROOT."packages/build/details/?invalid=true");	
	} else {
		BigTree::redirect(DEVELOPER_ROOT."packages/build/components/");
	}
?>