<?
	$admin->deletePageDraft(end($bigtree["path"]));
	BigTree::redirect(ADMIN_ROOT."pages/edit/".$page."/");
?>