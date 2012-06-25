<?
	if (isset($options["simple"]) && $options["simple"]) {
		$bigtree["simple_html_fields"][] = "field_$key";
	} else {
		$bigtree["html_fields"][] = "field_$key";
	}
	
	include BigTree::path("admin/form-field-types/draw/textarea.php");
?>