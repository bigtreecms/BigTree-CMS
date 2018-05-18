<?php	
	// Remove "The "
	$bigtree["post_data"][$field["settings"]["source"]] = str_ireplace("The ", "", $bigtree["post_data"][$field["settings"]["source"]]);
	
	include "../core/admin/form-field-types/process/route.php";
