<?
	if (end($bigtree["path"]) != "password" && $bigtree["path"][2] != "profile") {
		$admin->requireLevel(1);
	}
?>