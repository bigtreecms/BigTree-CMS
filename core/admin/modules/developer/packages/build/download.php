<?
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$cms->urlify($title).'.zip"');
	header('Content-Transfer-Encoding: binary');
	header('Connection: Keep-Alive');
	header('Content-Length: '.filesize(SERVER_ROOT."cache/package.zip"));
	ob_clean();
	flush();
	readfile(SERVER_ROOT."cache/package.zip");
	die();
?>