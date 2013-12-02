<?
	$admin->requireLevel(1);
	$resource = $admin->getResourceByFile($_POST["file"]);
	if ($resource) {
		$admin->deleteResource($resource["id"]);
	}
?>