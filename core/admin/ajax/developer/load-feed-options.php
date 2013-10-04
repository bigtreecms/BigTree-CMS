<?
	$table = $_POST["table"];
	$t = $_POST["type"];
	$d = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);
	$data = $d;

	$path = BigTree::path("admin/ajax/developer/feed-options/".$t.".php");
	if (file_exists($path)) {
		include $path;
	}
?>