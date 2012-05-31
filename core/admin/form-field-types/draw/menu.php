<?
	$options["fields"] = array(
		array("title" => "Title", "key" => "title", "type" => "text"),
		array("title" => "Link (include http://)", "key" => "link", "type" => "text")
	);
	
	include BigTree::path("admin/form-field-types/draw/array.php");
?>