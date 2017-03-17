<?
	$admin->verifyCSRFToken();
	
	foreach ($_POST as $key => $val) {
		$_SESSION["bigtree_admin"]["developer"]["package"][$key] = $val;
	}
	// Force these since they won't be overwritten
	$_SESSION["bigtree_admin"]["developer"]["package"]["license"] = $_POST["license"];
	$_SESSION["bigtree_admin"]["developer"]["package"]["licenses"] = $_POST["licenses"];

	if (!ctype_alnum(str_replace(array("-","_","."),"",$_POST["id"]))) {
		$admin->growl("Developer","Extension ID contains invalid characters.");
		BigTree::redirect(DEVELOPER_ROOT."extensions/build/details/?invalid=true");	
	} else {
		BigTree::redirect(DEVELOPER_ROOT."extensions/build/components/");
	}
?>