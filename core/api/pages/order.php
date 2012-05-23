<?
	/*
	|Name: Order Pages|
	|Description: Sets the navigation position of the passed in pages.  The order of the passed in array should match the desired navigation order.  If "pages" is a string instead of an array it should be a comma separated list of page IDs.|
	|Readonly: NO|
	|Level: 1|
	|Parameters: 
		pages: Array of Page IDs|
	|Returns:|
	*/
	
	$admin->requireAPIWrite();
	$admin->requireAPILevel(1);

	$page_array = $_POST["pages"];
	if (!is_array($page_array))
		$page_array = explode(",",$_POST["pages"]);
		
	$max = count($page_array);
	
	$x = 0;
	while ($x < $max) {
		sqlquery("UPDATE bigtree_pages SET position = '".($max-$x)."' WHERE id = '".mysql_real_escape_string($page_array[$x])."'");
		$x++;
	}	
	
	echo BigTree::apiEncode(array("success" => true));
?>