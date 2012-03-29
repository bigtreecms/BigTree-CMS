<?
	$admin->deletePageDraft(end($path));
	header("Location: ".$admin_root."pages/edit/".$page."/");
?>