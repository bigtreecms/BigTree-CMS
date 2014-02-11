<?
	
	// Remove "The "
	$bigtree["post_data"][$field["options"]["source"]] = str_ireplace("The ", "", $bigtree["post_data"][$field["options"]["source"]]);
	
	include "../core/admin/form-field-types/process/route.php";
	
?>