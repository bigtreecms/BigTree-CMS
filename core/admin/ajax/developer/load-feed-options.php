<?
	// Prevent including files outside feed-options
	$type = BigTree::cleanFile($_POST["type"]);

	$table = $_POST["table"];
	$data = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);

	$path = BigTree::path("admin/ajax/developer/feed-options/$type.php");
	if (file_exists($path)) {
		include $path;
	}
?>