<?
	$resource = $admin->getResourceByFile($_POST["file"]);
	$admin->updateResource($resource["id"],array("name" => $_POST["title"]));
?>