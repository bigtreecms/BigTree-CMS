<?
	if (isset($field["options"]["simple"]) && $field["options"]["simple"]) {
		$bigtree["simple_html_fields"][] = $field["id"];
	} else {
		$bigtree["html_fields"][] = $field["id"];
	}
	
	include BigTree::path("admin/form-field-types/draw/textarea.php");
?>