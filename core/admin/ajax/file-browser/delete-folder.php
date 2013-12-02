<?
	$admin->requireLevel(1);
	$folder = $admin->getResourceFolder($_POST["folder"]);
	$admin->deleteResourceFolder($_POST["folder"]);
	echo $folder["parent"] ? $folder["parent"] : 0;
?>