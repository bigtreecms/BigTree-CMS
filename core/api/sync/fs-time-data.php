<?
	/*
	|Name: Get Filesystem Time Data|
	|Description: Returns a serialized array of file paths as keys with their modification times as values.|
	|Readonly: YES|
	|Level: 2|
	|Parameters: |
	|Returns:
		data: Serialized Filesystem Time Data|
	*/
	
	$admin->apiRequireLevel(2);

	$ignored = array(
		$site_root."files/admin-cache/",
		$server_root."core/",
		$server_root."backup.sql",
		$server_root."index.php",
		$server_root."cache/",
		$server_root."templates/config.php"
	);
	function bigtree_recurse_timestamps($directory) {
		global $ignored;
		$data = array();
		$o = opendir($directory);
		while ($f = readdir($o)) {
			if ($f != "." && $f != "..") {
				if (is_dir($directory.$f)) {
					if (!in_array($directory.$f."/",$ignored))
						$data = array_merge($data,bigtree_recurse_timestamps($directory.$f."/"));
				} else {
					if (!in_array($directory.$f,$ignored))
						$data[str_replace($GLOBALS["server_root"],"/",$directory.$f)] = filemtime($directory.$f);
				}
			}
		}
		return $data;
	}
	
	$data = serialize(bigtree_recurse_timestamps($server_root));
	
	echo BigTree::apiEncode(array("success" => true,"data" => $data));
?>