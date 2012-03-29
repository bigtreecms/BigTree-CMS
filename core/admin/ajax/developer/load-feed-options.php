<?
	$table = $_POST["table"];
	$t = $_POST["type"];
	$d = json_decode($_POST["data"],true);

	$path = BigTree::path("admin/ajax/developer/feed-options/".$t.".php");
	if (file_exists($path)) {
		include $path;
	}
?>