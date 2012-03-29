<?
	if ($options["simple"]) {
		$simplehtmls[] = "field_$key";
	} else {
		$htmls[] = "field_$key";
	}
	
	include BigTree::path("admin/form-field-types/draw/textarea.php");
?>