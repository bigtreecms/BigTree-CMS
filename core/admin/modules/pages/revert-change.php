<?
	$admin->deletePageDraft(end($bigtree["path"]));
	header("Location: ".ADMIN_ROOT."pages/edit/".$page."/");
?>