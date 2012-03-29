<?
	$resource = $admin->getResourceByFile($_POST["file"]);
	$admin->updateResource($resource["id"],$_POST["title"]);
?>