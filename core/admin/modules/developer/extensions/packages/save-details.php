<?
	foreach ($_POST as $key => $val) {
		$_SESSION["bigtree_admin"]["developer"]["package"][$key] = $val;
	}

	BigTree::redirect(DEVELOPER_ROOT."extensions/packages/review/");
?>